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

        return Inertia::render('Billing/Invoices/Index', [
            'invoices'  => $invoices,
            'yearMonth' => $yearMonth,
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
            'year_month' => 'required|date_format:Y-m',
        ]);

        $facilityId = $this->facilityId();
        $period = BillingPeriod::where('facility_id', $facilityId)
            ->where('year_month', $request->year_month)
            ->firstOrFail();

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

    private function authorizeFacility(GuardianInvoice $invoice): void
    {
        abort_if($invoice->facility_id !== $this->facilityId(), 403);
    }
}
