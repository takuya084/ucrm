<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Child;
use App\Models\UsageRecord;
use App\Models\RecipientCertificate;
use App\Models\MonitoringRecord;
use App\Models\SupportPlan;
use App\Models\Inquiry;

class DashboardController extends Controller
{
    public function index()
    {
        $today = date('Y-m-d');
        $facilityId = auth()->user()->staff?->facility_id;

        // 今日の出席サマリー
        $todayAttended = UsageRecord::when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->where('date', $today)
            ->where('status', 'attended')
            ->count();

        $todayTotal = UsageRecord::when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->where('date', $today)
            ->count();

        $todayWithSupport = UsageRecord::when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->where('date', $today)
            ->whereHas('supportRecord')
            ->count();

        // 利用児童数（アクティブ）
        $activeChildren = Child::when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->where('contract_status', 'active')
            ->count();

        // 受給者証の期限アラート（30日以内）
        $expiringCertificates = RecipientCertificate::where('status', 'active')
            ->when($facilityId, fn($q) => $q->whereHas('child', fn($q2) => $q2->where('facility_id', $facilityId)))
            ->whereDate('valid_to', '>=', $today)
            ->whereDate('valid_to', '<=', date('Y-m-d', strtotime('+30 days')))
            ->with('child:id,name')
            ->get(['id', 'child_id', 'valid_to']);

        // モニタリング期限アラート（30日以内 + 超過）
        $monitoringDue = MonitoringRecord::where('next_review_date', '<=', date('Y-m-d', strtotime('+30 days')))
            ->when($facilityId, fn($q) => $q->whereHas('child', fn($q2) => $q2->where('facility_id', $facilityId)))
            ->with('child:id,name')
            ->orderBy('next_review_date')
            ->get(['id', 'child_id', 'next_review_date', 'monitoring_date']);

        // 個別支援計画：同意待ち
        $pendingAgreements = SupportPlan::where('guardian_agreement', false)
            ->when($facilityId, fn($q) => $q->whereHas('child', fn($q2) => $q2->where('facility_id', $facilityId)))
            ->with('child:id,name')
            ->orderBy('valid_from')
            ->get(['id', 'child_id', 'valid_from', 'valid_to', 'plan_date']);

        // 問い合わせ：未対応
        $openInquiries = Inquiry::whereIn('status', ['open', 'in_progress'])
            ->when($facilityId, fn($q) => $q->whereHas('child', fn($q2) => $q2->where('facility_id', $facilityId)))
            ->with('child:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'child_id', 'category', 'status', 'created_at']);

        return Inertia::render('Dashboard', [
            'todayStats' => [
                'attended'    => $todayAttended,
                'total'       => $todayTotal,
                'withSupport' => $todayWithSupport,
                'date'        => $today,
            ],
            'activeChildren'      => $activeChildren,
            'expiringCertificates' => $expiringCertificates,
            'monitoringDue'       => $monitoringDue,
            'pendingAgreements'   => $pendingAgreements,
            'openInquiries'       => $openInquiries,
        ]);
    }
}
