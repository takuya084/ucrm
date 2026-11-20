<?php

namespace App\Http\Controllers;

use App\Services\DailyAttendanceRoster;
use App\Services\DailyTransportOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransportController extends Controller
{
    public function index(Request $request, DailyAttendanceRoster $roster, DailyTransportOverview $transport)
    {
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $input['date'] ?? today()->toDateString();
        $facilityId = $this->facilityId();
        $canManage = in_array(auth()->user()->staff?->role, ['admin', 'leader'], true);
        $visits = $roster->forDate($facilityId, $date)['rows']
            ->filter(fn ($row) => $row['status'] === 'attended' && $row['child_name'] !== null)
            ->unique('child_id')->map(fn ($row) => [
                'childId' => $row['child_id'], 'name' => $row['child_name'],
                'pickup' => $row['yoyaku_pickup_time'], 'dropoff' => $row['yoyaku_dropoff_time'],
                'pickupLocation' => $row['yoyaku_pickup_location'], 'dropoffLocation' => $row['yoyaku_dropoff_location'],
            ])->values();

        return Inertia::render('Transport/Index', [
            'date' => $date,
            'canManage' => $canManage,
            ...$transport->forDate($facilityId, $date, $visits, $canManage),
        ]);
    }
}
