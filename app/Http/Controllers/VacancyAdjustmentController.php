<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\ChildSchedule;
use App\Models\Facility;
use App\Models\UsageRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VacancyAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $date       = $request->input('date', date('Y-m-d'));
        $dayOfWeek  = $this->getDayOfWeek($date);
        $facilityId = $this->facilityId();
        $yearMonth  = substr($date, 0, 7); // 'Y-m'

        $capacity = Facility::find($facilityId)?->capacity_per_day ?? 10;

        // その日のスケジュール対象児童ID
        $scheduledChildIds = ChildSchedule::where('day_of_week', $dayOfWeek)
            ->where('start_date', '<=', $date)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId)->where('contract_status', 'active'))
            ->pluck('child_id');

        // その日の出席記録
        $usageMap = UsageRecord::where('date', $date)
            ->whereIn('child_id', $scheduledChildIds)
            ->get()
            ->keyBy('child_id');

        $attended       = $usageMap->where('status', 'attended')->count();
        $absentRecorded = $usageMap->where('status', '!=', 'attended')->count();
        $notRecorded    = $scheduledChildIds->count() - $usageMap->count();
        $availableSlots = max(0, $capacity - $attended);

        // 欠席者（スケジュールあり → 欠席または未記録）
        $absentChildIds = $scheduledChildIds->filter(fn($id) =>
            !$usageMap->has($id) || $usageMap->get($id)->status !== 'attended'
        );

        $absentChildren = Child::whereIn('id', $absentChildIds)
            ->with(['guardians' => fn($q) => $q->orderByPivot('priority_order')])
            ->get()
            ->map(fn($child) => [
                'id'             => $child->id,
                'name'           => $child->name,
                'grade'          => $child->grade,
                'pickup_required'=> $child->pickup_required,
                'status'         => $usageMap->get($child->id)?->status ?? 'not_recorded',
                'absent_reason'  => $usageMap->get($child->id)?->absent_reason,
                'guardian'       => $this->formatGuardian($child->guardians->first()),
            ])
            ->values();

        // 連絡候補（その日スケジュールなし・契約中・今月残日数あり）
        $candidates = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->whereNotIn('id', $scheduledChildIds)
            ->with([
                'guardians'                => fn($q) => $q->orderByPivot('priority_order'),
                'activeRecipientCertificate',
                'schedules'                => fn($q) => $q
                    ->where('start_date', '<=', $date)
                    ->where(fn($q2) => $q2->whereNull('end_date')->orWhere('end_date', '>=', $date)),
            ])
            ->get()
            ->map(function ($child) use ($yearMonth) {
                $cert        = $child->activeRecipientCertificate;
                $usedDays    = UsageRecord::where('child_id', $child->id)
                    ->where('billing_target', true)
                    ->where('status', 'attended')
                    ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$yearMonth])
                    ->count();
                $monthlyLimit   = $cert?->monthly_limit ?? 0;
                $remainingDays  = max(0, $monthlyLimit - $usedDays);

                return [
                    'id'              => $child->id,
                    'name'            => $child->name,
                    'grade'           => $child->grade,
                    'pickup_required' => $child->pickup_required,
                    'schedule_days'   => $child->schedules->pluck('day_of_week')->toArray(),
                    'monthly_limit'   => $monthlyLimit,
                    'used_days'       => $usedDays,
                    'remaining_days'  => $remainingDays,
                    'cert_valid_to'   => $cert?->valid_to,
                    'guardian'        => $this->formatGuardian($child->guardians->first()),
                ];
            })
            ->sortByDesc('remaining_days')
            ->values();

        return Inertia::render('VacancyAdjustment/Index', [
            'date'           => $date,
            'dayName'        => $this->dayName($dayOfWeek),
            'stats'          => [
                'capacity'       => $capacity,
                'scheduled'      => $scheduledChildIds->count(),
                'attended'       => $attended,
                'absent'         => $absentRecorded + $notRecorded,
                'availableSlots' => $availableSlots,
            ],
            'absentChildren' => $absentChildren,
            'candidates'     => $candidates,
        ]);
    }

    private function formatGuardian($guardian): ?array
    {
        if (!$guardian) return null;
        return [
            'name'              => $guardian->name,
            'relationship'      => $guardian->relationship,
            'tel_primary'       => $guardian->tel_primary,
            'tel_secondary'     => $guardian->tel_secondary,
            'preferred_contact' => $guardian->preferred_contact,
        ];
    }

    private function getDayOfWeek(string $date): string
    {
        $map = ['Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue',
                'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'];
        return $map[date('D', strtotime($date))] ?? 'mon';
    }

    private function dayName(string $day): string
    {
        $names = ['mon' => '月', 'tue' => '火', 'wed' => '水',
                  'thu' => '木', 'fri' => '金', 'sat' => '土', 'sun' => '日'];
        return $names[$day] ?? '';
    }
}
