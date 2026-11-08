<?php

namespace App\Services;

use App\Models\ContactNote;
use App\Models\SupportRecord;
use App\Models\UsageRecord;

class DailyWorkService
{
    public function forDate(int $facilityId, string $date): array
    {
        $usage = UsageRecord::where('facility_id', $facilityId)->whereDate('date', $date)
            ->whereHas('child', fn ($q) => $q->where('facility_id', $facilityId))
            ->with('child:id,name,pickup_required')->get()->keyBy('child_id');
        $notes = ContactNote::where('facility_id', $facilityId)->whereDate('date', $date)
            ->whereHas('child', fn ($q) => $q->where('facility_id', $facilityId))
            ->with('child:id,name,pickup_required')->get()->keyBy('child_id');
        $records = SupportRecord::whereDate('date', $date)
            ->whereHas('child', fn ($q) => $q->where('facility_id', $facilityId))
            ->get(['id', 'child_id'])->keyBy('child_id');
        $rows = $usage->keys()->merge($notes->keys())->unique()->map(function ($id) use ($usage, $notes, $records, $date) {
            $ur = $usage->get($id); $note = $notes->get($id); $record = $records->get($id);
            $child = $ur?->child ?? $note->child;
            $attended = $ur?->status === 'attended';
            $state = ContactNoteStatus::for($note);
            return [
                'child_id' => $id, 'child_name' => $child->name,
                'attendance' => $ur ? (UsageRecord::STATUS_LABELS[$ur->status] ?? $ur->status) : '出欠未確定',
                'transport' => !$attended || !$child->pickup_required ? '送迎対象外' : (($ur->pickup_done ? '迎え完了' : '迎え未完了').'・'.($ur->dropoff_done ? '送り完了' : '送り未完了')),
                'state' => $state,
                'record' => $record ? '保存済み' : ($attended ? '未入力' : '対象外'),
                'record_url' => $record ? route('support-records.edit', $record->id) : ($attended ? route('support-records.create', ['usage_record_id' => $ur->id]) : null),
                'note_url' => route('contact-notes.index', ['date' => $date]).'#child-'.$id,
                'tasks' => [
                    'home' => $state['homePending'],
                    'record' => $attended && !$record,
                    'report' => $attended && $record && !($note?->hasFacilityContent()),
                    'draft' => $note && $note->hasFacilityContent() && !$note->isPublished() && !$note->withdrawn_at,
                    'delivery' => (bool) ($state['delivery'] && str_contains($state['delivery'], '要確認')),
                ],
            ];
        })->sortBy('child_name')->values();
        return ['rows' => $rows, 'hasAttendance' => $usage->isNotEmpty(), 'counts' => collect(['home','record','report','draft','delivery'])->mapWithKeys(fn ($task) => [$task => $rows->filter(fn ($row) => $row['tasks'][$task])->count()])->all()];
    }
}
