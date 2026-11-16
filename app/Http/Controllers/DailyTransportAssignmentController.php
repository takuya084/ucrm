<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DailyTransportAssignmentController extends Controller
{
    public function update(Request $request)
    {
        $facilityId = $this->facilityId();
        $input = $request->validate([
            'service_date' => ['required', 'date_format:Y-m-d'],
            'child_id' => ['required', 'integer', Rule::exists('children', 'id')
                ->where('facility_id', $facilityId)->whereNull('deleted_at')],
            'direction' => ['required', Rule::in(['pickup', 'dropoff'])],
            'staff_id' => ['present', 'nullable', 'integer', Rule::exists('staff', 'id')
                ->where('facility_id', $facilityId)->where('is_active', true)],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $key = [
            'facility_id' => $facilityId,
            'service_date' => $input['service_date'],
            'child_id' => $input['child_id'],
            'direction' => $input['direction'],
        ];

        if ($input['staff_id'] ?? null) {
            DB::table('daily_transport_assignments')->upsert([
                $key + [
                    'staff_id' => $input['staff_id'],
                    'scheduled_time' => $input['scheduled_time'] ?? null,
                    'location' => $input['location'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ], array_keys($key), ['staff_id', 'scheduled_time', 'location', 'updated_at']);
        } else {
            DB::table('daily_transport_assignments')->where($key)->delete();
        }

        return to_route('dashboard', ['date' => $input['service_date']]);
    }
}
