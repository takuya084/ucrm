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
            ]);
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
