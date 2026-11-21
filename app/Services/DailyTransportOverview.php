<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyTransportOverview
{
    public function __construct(private SharedTransportBoard $transport) {}

    public function forDate(int $facilityId, string $date, Collection $visits, bool $canManage): array
    {
        $assignments = DB::table('daily_transport_assignments as assignments')
            ->join('staff', 'staff.id', '=', 'assignments.staff_id')
            ->leftJoin('staff as assistant', fn ($join) => $join->on('assistant.id', '=', 'assignments.assistant_staff_id')->where('assistant.facility_id', $facilityId))
            ->leftJoin('children', 'children.id', '=', 'assignments.child_id')
            ->leftJoin('schools', 'schools.id', '=', 'children.school_id')
            ->where('assignments.facility_id', $facilityId)
            ->where('staff.facility_id', $facilityId)
            ->where('assignments.service_date', $date)
            ->whereIn('assignments.child_id', $visits->pluck('childId'))
            ->orderByRaw('CASE WHEN assignments.scheduled_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('assignments.scheduled_time')
            ->orderBy('assignments.id')
            ->get([
                'assignments.child_id', 'assignments.staff_id', 'assignments.direction',
                'assignments.scheduled_time', 'assignments.location', 'staff.name as staff_name',
                'assignments.assistant_staff_id', 'assistant.name as assistant_staff_name', 'schools.name as school_name',
            ]);
        $assignments = $assignments->map(function ($row) {
            if (in_array($row->location, ['学校', 'school'], true)) $row->location = $row->school_name ?: '学校（名称未登録）';
            return $row;
        })->sort(fn ($a, $b) => strcmp($a->scheduled_time ?? '99:99', $b->scheduled_time ?? '99:99') ?: strcmp($a->location ?? '', $b->location ?? '') ?: $a->child_id <=> $b->child_id)->values();
        $transportStaff = $canManage
            ? Staff::where('facility_id', $facilityId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : [];

        $sharedTransport = $this->transport->forDate($facilityId, $date);
        $transportVisits = $visits;
        $transportState = 'local';
        $localTransportCount = DB::table('daily_transport_assignments')->where('facility_id', $facilityId)->whereDate('service_date', $date)->count();
        if ($sharedTransport !== null) {
            $assignments = $sharedTransport['assignments'];
            $transportStaff = $canManage ? $sharedTransport['staff'] : [];
            $transportVisits = $sharedTransport['visits'];
            $transportState = $sharedTransport['state'];
        }

        return [
            'transportAssignments' => $assignments,
            'transportStaff' => $transportStaff,
            'transportVisits' => $transportVisits,
            'transportState' => $transportState,
            'localTransportCount' => $sharedTransport !== null ? $localTransportCount : 0,
        ];
    }
}
