<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStaffWorkPatternRequest;
use App\Models\ShiftLabel;
use App\Models\Staff;
use App\Models\StaffWorkPattern;
use Inertia\Inertia;

class StaffWorkPatternController extends Controller
{
    public function edit(Staff $staff)
    {
        abort_if($staff->facility_id !== $this->facilityId(), 403);

        $patterns = $staff->workPatterns->keyBy('day_of_week');

        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $patternData = [];
        foreach ($days as $day) {
            $p = $patterns->get($day);
            $patternData[] = [
                'day_of_week' => $day,
                'start_time'  => $p?->start_time ? substr($p->start_time, 0, 5) : null,
                'work_type'   => $p?->work_type ?? '',
            ];
        }

        $labels = ShiftLabel::where('facility_id', $this->facilityId())
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['name', 'is_off']);

        return Inertia::render('StaffWorkPatterns/Edit', [
            'staff'      => $staff->only('id', 'name'),
            'patterns'   => $patternData,
            'dayLabels'  => StaffWorkPattern::DAY_LABELS,
            'labels'     => $labels,
        ]);
    }

    public function update(UpdateStaffWorkPatternRequest $request, Staff $staff)
    {
        abort_if($staff->facility_id !== $this->facilityId(), 403);

        foreach ($request->validated()['patterns'] as $pattern) {
            StaffWorkPattern::updateOrCreate(
                [
                    'staff_id'    => $staff->id,
                    'day_of_week' => $pattern['day_of_week'],
                ],
                [
                    'facility_id' => $this->facilityId(),
                    'start_time'  => $pattern['start_time'],
                    'work_type'   => $pattern['work_type'] ?? '',
                ]
            );
        }

        return to_route('staff.work-patterns.edit', $staff)
            ->with(['message' => '勤務パターンを保存しました。', 'status' => 'success']);
    }
}
