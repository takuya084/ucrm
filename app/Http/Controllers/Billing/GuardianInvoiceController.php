<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingPeriod;
use App\Models\GuardianInvoice;
use App\Services\Billing\GuardianInvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GuardianInvoiceController extends Controller
{
    public function __construct(
        private GuardianInvoicePdfService $pdfService,
    ) {}

    /**
     * 利用者請求一覧
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();
        $yearMonth  = $request->input('month', now()->format('Y-m'));

        $invoices = GuardianInvoice::where('facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with(['child:id,name,name_kana', 'guardian:id,name'])
            ->orderBy('child_id')
            ->get();

        $paidSum = $invoices->whereIn('payment_status', ['paid', 'partial'])
            ->sum(fn ($i) => $i->paid_amount ?? ($i->payment_status === 'paid' ? $i->total_amount : 0));

        return Inertia::render('Billing/Invoices/Index', [
            'invoices'  => $invoices,
            'yearMonth' => $yearMonth,
            'summary'   => [
                'count'        => $invoices->count(),
                'total'        => (int) $invoices->sum('total_amount'),
                'paid'         => (int) $paidSum,
                'outstanding'  => (int) $invoices->sum('total_amount') - (int) $paidSum,
                'unpaid_count' => $invoices->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])->count(),
            ],
        ]);
    }

    /**
     * 請求書プレビュー
     */
    public function show(GuardianInvoice $guardianInvoice)
    {
        $this->authorizeFacility($guardianInvoice);

        $guardianInvoice->load([
            'billingDetail.billingDetailLines',
            'child',
            'guardian',
            'facility',
        ]);

        return Inertia::render('Billing/Invoices/Show', [
            'invoice' => $guardianInvoice,
        ]);
    }

    /**
     * 請求書一括生成
     */
    public function generate(Request $request)
    {
        $request->validate([
            'year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $facilityId = $this->facilityId();
        $period = BillingPeriod::where('facility_id', $facilityId)
            ->where('year_month', $request->year_month)
            ->first();

        if (!$period) {
            return back()->with(['message' => '該当月の請求データがありません。先に月次請求で計算を実行してください。', 'status' => 'error']);
        }

        $invoices = $this->pdfService->createInvoicesFromBillingPeriod($period);

        session()->flash('message', count($invoices) . '件の請求書を生成しました。');
        session()->flash('status', 'success');

        return to_route('billing.invoices.index', ['month' => $request->year_month]);
    }

    /**
     * PDF請求書ダウンロード
     */
    public function downloadPdf(GuardianInvoice $guardianInvoice)
    {
        $this->authorizeFacility($guardianInvoice);

        $path = $this->pdfService->generateInvoice($guardianInvoice);

        return Storage::disk('local')->download($path);
    }

    /**
     * 入金管理の更新
     */
    public function updatePayment(Request $request, GuardianInvoice $guardianInvoice)
    {
        $this->authorizeFacility($guardianInvoice);

        $validated = $request->validate([
            'payment_status' => 'required|in:unpaid,paid,partial,overdue',
            'payment_method' => 'nullable|in:bank_transfer,cash,other',
            'paid_amount'    => 'nullable|integer|min:0',
            'paid_at'        => 'nullable|date',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $guardianInvoice->update($validated);

        session()->flash('message', '入金情報を更新しました。');
        session()->flash('status', 'success');

        return back();
    }

    /**
     * 領収書PDF（入金記録がある請求書のみ）
     */
    public function receiptPdf(GuardianInvoice $guardianInvoice, \App\Services\Billing\GuardianReceiptPdfService $receiptService)
    {
        $this->authorizeFacility($guardianInvoice);

        abort_if(
            !in_array($guardianInvoice->payment_status, ['paid', 'partial'], true) || !$guardianInvoice->paid_at,
            422,
            '領収書は入金記録（入金状態・入金日）を登録した後に発行できます。'
        );

        \App\Models\AuditLog::record('exported', $guardianInvoice);
        $path = $receiptService->generate($guardianInvoice);

        return Storage::disk('local')->download($path);
    }

    private function authorizeFacility(GuardianInvoice $invoice): void
    {
        abort_if($invoice->facility_id !== $this->facilityId(), 403);
    }
}
