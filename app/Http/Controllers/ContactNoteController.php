<?php

namespace App\Http\Controllers;

use App\Jobs\PushContactNote;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ContactNote;
use App\Models\Facility;
use App\Models\UsageRecord;
use App\Services\ContactNotePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContactNoteController extends Controller
{
    /**
     * 連絡帳一覧（日付ベース）。
     * 当日の出席児童と連絡帳の状態（未作成/下書き/公開済み/既読/家庭側記入）を一覧する。
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();
        $date       = $request->date ?: today()->format('Y-m-d');

        $usageRecords = UsageRecord::where('facility_id', $facilityId)
            ->whereDate('date', $date)
            ->with('child:id,name')
            ->get();

        $notes = ContactNote::where('facility_id', $facilityId)
            ->whereDate('date', $date)
            ->with(['child:id,name', 'staff:id,name'])
            ->get()
            ->keyBy('child_id');

        // 出席児童 + 出席レコードはないが連絡帳がある児童（家庭側記入が先行したケース）
        $rows = $usageRecords->map(function ($ur) use ($notes) {
            $note = $notes->get($ur->child_id);
            return [
                'child_id'              => $ur->child_id,
                'child_name'            => $ur->child?->name,
                'usage_record_id'       => $ur->id,
                'note'                  => $note,
            ];
        });

        $noteOnly = $notes->keys()->diff($usageRecords->pluck('child_id'));
        foreach ($noteOnly as $childId) {
            $note = $notes->get($childId);
            $rows->push([
                'child_id'        => $childId,
                'child_name'      => $note->child?->name,
                'usage_record_id' => null,
                'note'            => $note,
            ]);
        }

        // 年間PDF出力用: 連絡帳が存在する年と契約中の児童一覧
        // （本番MySQLとテストSQLiteで年抽出の関数が異なる）
        $yearExpr = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y', date)"
            : 'YEAR(date)';
        $years = ContactNote::where('facility_id', $facilityId)
            ->selectRaw("DISTINCT {$yearExpr} as y")
            ->pluck('y')
            ->filter()
            ->map(fn ($y) => (string) $y)
            ->sortDesc()
            ->values();
        if ($years->isEmpty()) {
            $years = collect([now()->format('Y')]);
        }

        $children = Child::where('facility_id', $facilityId)
            ->orderBy('name_kana')
            ->get(['id', 'name']);

        return Inertia::render('ContactNotes/Index', [
            'date'         => $date,
            'rows'         => $rows->sortBy('child_name')->values(),
            'statusLabels' => ContactNote::STATUS_LABELS,
            'exportYears'  => $years,
            'children'     => $children,
        ]);
    }

    /**
     * 児童×年の連絡帳PDFを出力（年末の保存運用向け）
     */
    public function exportYearly(Request $request, ContactNotePdfService $pdfService)
    {
        $facilityId = $this->facilityId();
        $validated  = $request->validate([
            'year'     => ['required', 'integer', 'min:2020', 'max:2100'],
            'child_id' => ['required', Rule::exists('children', 'id')->where('facility_id', $facilityId)],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $path  = $pdfService->generateYearly($child, (int) $validated['year']);

        if (!$path) {
            return back()->with([
                'message' => "{$validated['year']}年の連絡帳がありません。",
                'status'  => 'error',
            ]);
        }

        // 要配慮個人情報の一括出力のため監査ログに記録する
        AuditLog::record("exported_contact_notes_{$validated['year']}", $child);

        return Storage::disk('local')->download($path, "連絡帳_{$validated['year']}年_{$child->name}.pdf");
    }

    /**
     * 施設全児童分の連絡帳PDFをZIPで一括出力
     */
    public function exportYearlyZip(Request $request, ContactNotePdfService $pdfService)
    {
        $facilityId = $this->facilityId();
        $validated  = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $facility = Facility::findOrFail($facilityId);
        $path     = $pdfService->generateYearlyZip($facility, (int) $validated['year']);

        if (!$path) {
            return back()->with([
                'message' => "{$validated['year']}年の連絡帳がありません。",
                'status'  => 'error',
            ]);
        }

        AuditLog::record("exported_contact_notes_zip_{$validated['year']}", $facility);

        return Storage::disk('local')->download($path, "連絡帳_{$validated['year']}年_全児童.zip");
    }

    /**
     * 連絡帳を公開して p-yoyaku へ配信する
     */
    public function publish(ContactNote $contactNote)
    {
        abort_if($contactNote->facility_id !== $this->facilityId(), 403);

        if (!$contactNote->hasFacilityContent()) {
            return back()->with([
                'message' => '連絡帳に記入がないため公開できません。',
                'status'  => 'error',
            ]);
        }

        if (!$contactNote->isPublished()) {
            $contactNote->update([
                'status'       => ContactNote::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => auth()->user()->staff?->id,
            ]);
        }

        PushContactNote::dispatch($contactNote->id)->afterCommit();

        return back()->with([
            'message' => '連絡帳を公開しました。',
            'status'  => 'success',
        ]);
    }
}
