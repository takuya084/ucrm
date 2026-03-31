<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkSaveShiftRequest;
use App\Models\Child;
use App\Models\ChildSchedule;
use App\Models\Facility;
use App\Models\MonthlyShift;
use App\Models\ShiftDayNote;
use App\Models\ShiftEntry;
use App\Models\ShiftLabel;
use App\Models\Staff;
use App\Models\StaffQualification;
use App\Models\StaffWorkPattern;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShiftController extends Controller
{
    private const DOW_MAP = [
        0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed',
        4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    public function index(Request $request)
    {
        $facilityId = $this->facilityId();
        $year = (int) ($request->query('year') ?: date('Y'));

        $shifts = MonthlyShift::where('facility_id', $facilityId)
            ->where('year', $year)
            ->get(['id', 'month', 'status', 'created_at'])
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $s = $shifts->get($m);
            $months[] = [
                'month'      => $m,
                'id'         => $s?->id,
                'status'     => $s?->status,
                'created_at' => $s?->created_at?->format('Y-m-d'),
            ];
        }

        return Inertia::render('Shifts/Index', [
            'year'   => $year,
            'months' => $months,
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'year'  => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|between:1,12',
        ]);

        $facilityId = $this->facilityId();
        $year = (int) $request->year;
        $month = (int) $request->month;

        $existing = MonthlyShift::where('facility_id', $facilityId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing) {
            return to_route('shifts.edit', $existing);
        }

        $shift = DB::transaction(function () use ($facilityId, $year, $month) {
            $shift = MonthlyShift::create([
                'facility_id' => $facilityId,
                'year'        => $year,
                'month'       => $month,
                'status'      => 'draft',
                'created_by'  => auth()->user()->staff?->id,
            ]);

            $staffMembers = Staff::where('facility_id', $facilityId)
                ->where('is_active', true)
                ->with('workPatterns')
                ->get();

            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            $entries = [];
            $now = now()->format('Y-m-d H:i:s');

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                $dow = self::DOW_MAP[$date->dayOfWeek];

                foreach ($staffMembers as $staff) {
                    $pattern = $staff->workPatterns->firstWhere('day_of_week', $dow);

                    $entries[] = [
                        'monthly_shift_id' => $shift->id,
                        'staff_id'         => $staff->id,
                        'date'             => $date->toDateString(),
                        'start_time'       => $pattern?->start_time,
                        'work_type'        => $pattern?->work_type ?? ($dow === 'sun' ? '休み' : ''),
                        'note'             => null,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            }

            if (!empty($entries)) {
                ShiftEntry::insert($entries);
            }

            return $shift;
        });

        return to_route('shifts.edit', $shift)
            ->with(['message' => "{$year}年{$month}月のシフトを作成しました。", 'status' => 'success']);
    }

    public function edit(MonthlyShift $shift)
    {
        abort_if($shift->facility_id !== $this->facilityId(), 403);

        $staffMembers = Staff::where('facility_id', $shift->facility_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderByRaw("FIELD(role, 'admin', 'leader', 'staff', 'driver')")
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $daysInMonth = Carbon::create($shift->year, $shift->month)->daysInMonth;
        $dates = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($shift->year, $shift->month, $day);
            $dow = self::DOW_MAP[$date->dayOfWeek];
            $dates[] = [
                'date'        => $date->toDateString(),
                'day'         => $day,
                'day_of_week' => $dow,
                'day_label'   => StaffWorkPattern::DAY_LABELS[$dow],
            ];
        }

        $entries = $shift->entries()
            ->get(['staff_id', 'date', 'start_time', 'work_type', 'note'])
            ->map(function ($e) {
                return [
                    'staff_id'   => $e->staff_id,
                    'date'       => $e->date->toDateString(),
                    'start_time' => $e->start_time ? substr($e->start_time, 0, 5) : null,
                    'work_type'  => $e->work_type,
                    'note'       => $e->note,
                ];
            });

        $dayNotes = $shift->dayNotes()
            ->get(['date', 'note'])
            ->mapWithKeys(fn ($n) => [$n->date->toDateString() => $n->note]);

        $userRole = auth()->user()->staff?->role;
        $canEdit = in_array($userRole, ['admin', 'leader'], true)
            && $shift->isDraft();

        $labels = ShiftLabel::where('facility_id', $shift->facility_id)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['name', 'is_off']);

        // ── 人員配置チェック用データ ──
        $facility = Facility::find($shift->facility_id);

        // 利用予定人数: child_schedules ベース
        $childCounts = $this->buildChildCounts(
            $shift->facility_id, $shift->year, $shift->month, $daysInMonth, $dates
        );

        // スタッフ資格
        $staffQualifications = StaffQualification::whereIn('staff_id', $staffMembers->pluck('id'))
            ->get()
            ->groupBy('staff_id')
            ->map(fn ($items) => $items->pluck('qualification')->values());

        return Inertia::render('Shifts/Edit', [
            'shift'        => [
                'id'     => $shift->id,
                'year'   => $shift->year,
                'month'  => $shift->month,
                'status' => $shift->status,
            ],
            'staffMembers'         => $staffMembers,
            'dates'                => $dates,
            'entries'              => $entries,
            'dayNotes'             => $dayNotes,
            'labels'               => $labels,
            'canEdit'              => $canEdit,
            'childCounts'          => $childCounts,
            'staffQualifications'  => $staffQualifications,
            'qualificationTypes'   => StaffQualification::TYPES,
            'capacity'             => $facility->capacity_per_day,
        ]);
    }

    public function bulkSave(BulkSaveShiftRequest $request, MonthlyShift $shift)
    {
        abort_if($shift->facility_id !== $this->facilityId(), 403);

        DB::transaction(function () use ($request, $shift) {
            foreach ($request->entries as $entry) {
                ShiftEntry::updateOrCreate(
                    [
                        'monthly_shift_id' => $shift->id,
                        'staff_id'         => $entry['staff_id'],
                        'date'             => $entry['date'],
                    ],
                    [
                        'start_time' => $entry['start_time'],
                        'work_type'  => $entry['work_type'] ?? '',
                        'note'       => $entry['note'],
                    ]
                );
            }

            if ($request->has('day_notes')) {
                foreach ($request->day_notes as $dn) {
                    if (empty($dn['note'])) {
                        ShiftDayNote::where('monthly_shift_id', $shift->id)
                            ->where('date', $dn['date'])
                            ->delete();
                    } else {
                        ShiftDayNote::updateOrCreate(
                            ['monthly_shift_id' => $shift->id, 'date' => $dn['date']],
                            ['note' => $dn['note']]
                        );
                    }
                }
            }
        });

        return back()->with(['message' => 'シフトを保存しました。', 'status' => 'success']);
    }

    public function updateStatus(Request $request, MonthlyShift $shift)
    {
        abort_if($shift->facility_id !== $this->facilityId(), 403);

        $request->validate([
            'status' => 'required|in:draft,confirmed',
        ]);

        $shift->update(['status' => $request->status]);

        $label = $request->status === 'confirmed' ? '確定' : '下書き';

        return back()->with(['message' => "シフトを{$label}にしました。", 'status' => 'success']);
    }

    /**
     * 月内各日の利用予定児童数を算出
     */
    private function buildChildCounts(int $facilityId, int $year, int $month, int $daysInMonth, array $dates): array
    {
        $activeChildIds = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->pluck('id');

        // 曜日別スケジュール数を集計
        $schedulesByDow = ChildSchedule::whereIn('child_id', $activeChildIds)
            ->where('start_date', '<=', Carbon::create($year, $month, $daysInMonth)->toDateString())
            ->where(function ($q) use ($year, $month) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', Carbon::create($year, $month, 1)->toDateString());
            })
            ->get()
            ->groupBy('day_of_week')
            ->map(fn ($items) => $items->unique('child_id')->count());

        // usage_records がある日はそちらで上書き
        $usageCounts = UsageRecord::where('facility_id', $facilityId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereIn('status', ['attended', 'absent_notice'])
            ->select('date', DB::raw('COUNT(*) as cnt'))
            ->groupBy('date')
            ->pluck('cnt', 'date')
            ->mapWithKeys(fn ($cnt, $date) => [Carbon::parse($date)->toDateString() => $cnt]);

        $counts = [];
        foreach ($dates as $d) {
            $dateStr = $d['date'];
            if ($usageCounts->has($dateStr)) {
                $counts[$dateStr] = $usageCounts[$dateStr];
            } else {
                $counts[$dateStr] = $schedulesByDow[$d['day_of_week']] ?? 0;
            }
        }

        return $counts;
    }

    public function destroy(MonthlyShift $shift)
    {
        abort_if($shift->facility_id !== $this->facilityId(), 403);

        $shift->delete();

        return to_route('shifts.index', ['year' => $shift->year])
            ->with(['message' => "{$shift->year}年{$shift->month}月のシフトを削除しました。", 'status' => 'success']);
    }
}
