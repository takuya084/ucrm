<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Child;
use App\Models\UsageRecord;
use App\Models\Staff;
use App\Models\DailyActivityPlan;
use App\Models\RecipientCertificate;
use App\Models\MonitoringRecord;
use App\Models\SupportPlan;
use App\Models\Inquiry;
use App\Services\DailyAttendanceRoster;
use App\Services\DailyWorkService;

class DashboardController extends Controller
{
    public function index(Request $request, DailyWorkService $dailyWork, DailyAttendanceRoster $roster)
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $today = $validated['date'] ?? today()->toDateString();
        $facilityId = $this->facilityId();
        $staff = auth()->user()->staff;
        $canManage = in_array($staff?->role, ['admin', 'leader'], true);
        $attendance = $roster->forDate($facilityId, $today);
        $visits = $attendance['rows']
            ->filter(fn ($row) => $row['status'] === 'attended' && $row['child_name'] !== null)
            ->unique('child_id')
            ->map(fn ($row) => [
                'childId' => $row['child_id'],
                'name' => $row['child_name'],
                'arrival' => $row['check_in_time'],
                'departure' => $row['check_out_time'],
                'pickup' => $row['yoyaku_pickup_time'],
                'dropoff' => $row['yoyaku_dropoff_time'],
                'pickupLocation' => $row['yoyaku_pickup_location'],
                'dropoffLocation' => $row['yoyaku_dropoff_location'],
                'allergy' => $row['allergy_note'],
                'home' => $row['contact_note_home_entry'] ? $row['contact_note_state']['home'] : null,
            ])
            ->values();

        $assignments = DB::table('daily_transport_assignments')
            ->where('facility_id', $facilityId)
            ->where('service_date', $today)
            ->whereIn('child_id', $visits->pluck('childId'))
            ->orderBy('scheduled_time')
            ->get(['child_id', 'staff_id', 'direction', 'scheduled_time', 'location']);
        $myTransport = $assignments->where('staff_id', $staff->id)->values();
        $transportStaff = $canManage
            ? Staff::where('facility_id', $facilityId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : [];

        // 今日の出席サマリー
        $todayAttended = UsageRecord::where('facility_id', $facilityId)
            ->whereDate('date', $today)
            ->where('status', 'attended')
            ->count();

        $todayTotal = UsageRecord::where('facility_id', $facilityId)
            ->whereDate('date', $today)
            ->count();

        $todayWithSupport = UsageRecord::where('facility_id', $facilityId)
            ->whereDate('date', $today)
            ->whereHas('supportRecord')
            ->count();

        // 利用児童数（アクティブ）
        $activeChildren = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->count();

        $expiringCertificates = $monitoringDue = $pendingAgreements = $openInquiries = [];
        $billingPeriod = null;
        $billingMonth = today()->subMonthNoOverflow()->format('Y-m');

        if ($canManage) {
            // 受給者証の期限アラート（30日以内）
            $expiringCertificates = RecipientCertificate::where('status', 'active')
                ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId))
                ->whereDate('valid_to', '>=', today()->toDateString())
                ->whereDate('valid_to', '<=', date('Y-m-d', strtotime('+30 days')))
                ->with('child:id,name')
                ->orderBy('valid_to')
                ->get(['id', 'child_id', 'valid_to']);

            // モニタリング期限アラート（30日以内 + 超過）
            $monitoringDue = MonitoringRecord::where('next_review_date', '<=', date('Y-m-d', strtotime('+30 days')))
                ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId))
                ->with('child:id,name')
                ->orderBy('next_review_date')
                ->get(['id', 'child_id', 'next_review_date', 'monitoring_date']);

            // 個別支援計画：同意待ち
            $pendingAgreements = SupportPlan::where('guardian_agreement', false)
                ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId))
                ->with('child:id,name')
                ->orderBy('valid_from')
                ->get(['id', 'child_id', 'valid_from', 'valid_to', 'plan_date']);

            // 前月分の請求業務の進み具合（月初1〜10日が請求期間）
            $billingPeriod = \App\Models\BillingPeriod::where('facility_id', $facilityId)
                ->where('year_month', $billingMonth)
                ->first(['id', 'year_month', 'status']);

            // 問い合わせ：未対応
            $openInquiries = Inquiry::whereIn('status', ['open', 'in_progress'])
                ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId))
                ->with('child:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'child_id', 'category', 'status', 'created_at']);
        }

        return Inertia::render('Dashboard', [
            'date' => $today,
            'visits' => $visits,
            'attendanceSource' => $attendance['dataSource'],
            'canManage' => $canManage,
            'activityCategories' => DailyActivityPlan::CATEGORIES,
            'activities' => DailyActivityPlan::forDate($facilityId, $today),
            'myTransport' => $myTransport,
            'transportAssignments' => $canManage ? $assignments : [],
            'transportStaff' => $transportStaff,
            'calendarToday' => today()->toDateString(),
            'dailyWork' => $dailyWork->forDate($facilityId, $today),
            'todayStats' => [
                'attended'    => $todayAttended,
                'absent' => UsageRecord::where('facility_id', $facilityId)->whereDate('date', $today)->whereIn('status', ['absent','absent_notice'])->count(),
                'total'       => $todayTotal,
                'withSupport' => $todayWithSupport,
                'date'        => $today,
            ],
            'activeChildren'      => $activeChildren,
            'expiringCertificates' => $expiringCertificates,
            'monitoringDue'       => $monitoringDue,
            'pendingAgreements'   => $pendingAgreements,
            'openInquiries'       => $openInquiries,
            'billingMonth'        => $billingMonth,
            'billingPeriod'       => $billingPeriod,
        ]);
    }
}
