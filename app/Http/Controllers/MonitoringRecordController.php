<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ContactNote;
use App\Models\MonitoringRecord;
use App\Models\Child;
use App\Models\SupportRecord;
use App\Http\Requests\StoreMonitoringRecordRequest;
use App\Http\Requests\UpdateMonitoringRecordRequest;
use App\Services\MonitoringRecordPdfService;
use Illuminate\Support\Facades\Storage;

class MonitoringRecordController extends Controller
{
    public function create(Request $request, Child $child)
    {
        $this->authorizeChild($child);

        $child->load(['monitoringRecords' => fn($q) => $q->orderBy('monitoring_date', 'desc')->limit(1)]);
        $lastRecord = $child->monitoringRecords->first();

        return Inertia::render('MonitoringRecords/Create', [
            'child'       => $child->only(['id', 'name']),
            'lastRecord'  => $lastRecord,
            'insights'    => $this->buildInsights($child, $lastRecord),
        ]);
    }

    /**
     * 対象期間の支援記録・連絡帳から、モニタリング作成の判断材料を集計する。
     * - 様子（condition）の分布と推移
     * - 5領域タグの分布（支援計画の5領域とキーを揃えてある）
     * - 短期目標への手応え（◎○△）の分布と推移
     * - 保護者の連絡帳コメント（「保護者のニーズ」欄への引用候補）
     */
    private function buildInsights(Child $child, ?MonitoringRecord $lastRecord): array
    {
        $from = $lastRecord?->period_to?->format('Y-m-d')
            ?? $lastRecord?->monitoring_date?->format('Y-m-d')
            ?? now()->subMonths(6)->format('Y-m-d');
        $to = today()->format('Y-m-d');

        $records = SupportRecord::where('child_id', $child->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get(['id', 'date', 'condition']);

        $notes = ContactNote::where('child_id', $child->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $domainCounts = collect(array_keys(ContactNote::FIVE_DOMAIN_LABELS))
            ->mapWithKeys(fn ($key) => [$key => 0])->toArray();
        foreach ($notes as $note) {
            foreach ($note->five_domain_tags ?? [] as $tag) {
                if (array_key_exists($tag, $domainCounts)) {
                    $domainCounts[$tag]++;
                }
            }
        }

        return [
            'period_from'      => $from,
            'period_to'        => $to,
            'record_count'     => $records->count(),
            'note_count'       => $notes->count(),
            'condition_counts' => [
                'good'   => $records->where('condition', 'good')->count(),
                'normal' => $records->where('condition', 'normal')->count(),
                'poor'   => $records->where('condition', 'poor')->count(),
            ],
            'condition_timeline' => $records->map(fn ($r) => [
                'date'      => $r->date->format('m/d'),
                'condition' => $r->condition,
            ])->values(),
            'domain_counts'    => $domainCounts,
            'domain_labels'    => ContactNote::FIVE_DOMAIN_LABELS,
            'goal_progress_counts' => [
                'achieved'  => $notes->where('goal_progress', 'achieved')->count(),
                'partial'   => $notes->where('goal_progress', 'partial')->count(),
                'difficult' => $notes->where('goal_progress', 'difficult')->count(),
            ],
            'goal_progress_labels' => ContactNote::GOAL_PROGRESS_LABELS,
            'guardian_comments' => $notes->filter(fn ($n) => filled($n->guardian_comment))
                ->sortByDesc('date')
                ->take(10)
                ->map(fn ($n) => [
                    'date'    => $n->date->format('Y-m-d'),
                    'comment' => $n->guardian_comment,
                ])->values(),
        ];
    }

    public function store(StoreMonitoringRecordRequest $request, Child $child)
    {
        $this->authorizeChild($child);

        $data = $request->validated();
        $data['child_id'] = $child->id;
        $data['staff_id'] = auth()->user()->staff?->id;

        MonitoringRecord::create($data);

        return redirect()->route('children.show', $child->id)
            ->with(['message' => 'モニタリング記録を登録しました。', 'status' => 'success']);
    }

    public function show(Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $monitoringRecord->load('staff:id,name');

        return Inertia::render('MonitoringRecords/Show', [
            'child'  => $child->only(['id', 'name']),
            'record' => $monitoringRecord,
        ]);
    }

    public function edit(Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        return Inertia::render('MonitoringRecords/Edit', [
            'child'  => $child->only(['id', 'name']),
            'record' => $monitoringRecord,
        ]);
    }

    public function update(UpdateMonitoringRecordRequest $request, Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $monitoringRecord->update($request->validated());

        return redirect()->route('children.monitoring.show', [$child->id, $monitoringRecord->id])
            ->with(['message' => 'モニタリング記録を更新しました。', 'status' => 'success']);
    }

    public function destroy(Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $monitoringRecord->delete();

        return redirect()->route('children.show', $child->id)
            ->with(['message' => 'モニタリング記録を削除しました。', 'status' => 'success']);
    }

    public function pdf(Child $child, MonitoringRecord $monitoringRecord, MonitoringRecordPdfService $pdfService)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $path = $pdfService->generate($monitoringRecord);
        return Storage::disk('local')->download($path);
    }

    private function authorizeChild(Child $child): void
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
    }
}
