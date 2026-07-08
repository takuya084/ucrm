<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\ChildrenYoyakuLinkController;
use App\Http\Controllers\RecipientCertificateController;
use App\Http\Controllers\ChildScheduleController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\UsageRecordController;
use App\Http\Controllers\SupportRecordController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\MonitoringRecordController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\OperationRecordController;
use App\Http\Controllers\SupportPlanController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ExternalFacilityController;
use App\Http\Controllers\ProgramItemController;
use App\Http\Controllers\VacancyAdjustmentController;
use App\Http\Controllers\ProgramProgressController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\AiDraftController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SubscribeController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftLabelController;
use App\Http\Controllers\StaffWorkPatternController;
use App\Http\Controllers\Billing\BillingPeriodController;
use App\Http\Controllers\Billing\BillingDetailController;
use App\Http\Controllers\Billing\DailyServiceRecordController;
use App\Http\Controllers\Billing\CopaymentCapController;
use App\Http\Controllers\Billing\GuardianInvoiceController;
use App\Http\Controllers\Billing\ErrorClaimController;
use App\Http\Controllers\Billing\ClaimReturnController;
use App\Http\Controllers\Billing\FacilityServiceSettingController;
use App\Http\Controllers\Billing\MonthlyExpenseController;

// 施設設定（管理者のみ）
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('facility/edit',  [FacilityController::class, 'edit'])  ->name('facility.edit');
    Route::patch('facility',     [FacilityController::class, 'update'])->name('facility.update');
});

// 職員管理（管理者のみ）
Route::resource('staff', StaffController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified', 'role:admin']);

// シフト管理（閲覧: 全員、編集: leader以上、作成/削除/確定: admin）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('shifts',                    [ShiftController::class, 'index'])       ->name('shifts.index');
    Route::get('shifts/{shift}/edit',       [ShiftController::class, 'edit'])        ->name('shifts.edit');
});
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::post('shifts/{shift}/bulk-save', [ShiftController::class, 'bulkSave'])   ->name('shifts.bulk-save');
});
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::post('shifts/create',            [ShiftController::class, 'create'])      ->name('shifts.create');
    Route::patch('shifts/{shift}/status',   [ShiftController::class, 'updateStatus'])->name('shifts.update-status');
    Route::delete('shifts/{shift}',         [ShiftController::class, 'destroy'])     ->name('shifts.destroy');
});

// 勤務パターン管理（admin のみ）
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('staff/{staff}/work-patterns',   [StaffWorkPatternController::class, 'edit'])  ->name('staff.work-patterns.edit');
    Route::patch('staff/{staff}/work-patterns',  [StaffWorkPatternController::class, 'update'])->name('staff.work-patterns.update');
});

// シフトラベル管理（admin のみ）
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('shift-labels',                      [ShiftLabelController::class, 'index'])  ->name('shift-labels.index');
    Route::post('shift-labels',                     [ShiftLabelController::class, 'store'])  ->name('shift-labels.store');
    Route::patch('shift-labels/{shiftLabel}',       [ShiftLabelController::class, 'update']) ->name('shift-labels.update');
    Route::delete('shift-labels/{shiftLabel}',      [ShiftLabelController::class, 'destroy'])->name('shift-labels.destroy');
});

// 利用児童管理（閲覧: 全員、編集: leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('children/create',              [ChildrenController::class, 'create'])->name('children.create');
    Route::post('children',                    [ChildrenController::class, 'store'])->name('children.store');
    Route::get('children/bulk',                [ChildrenController::class, 'createBulk'])->name('children.bulk');
    Route::post('children/bulk',               [ChildrenController::class, 'storeBulk'])->name('children.bulk.store');
    Route::get('children/bulk/template',       [ChildrenController::class, 'bulkTemplate'])->name('children.bulk.template');
    Route::get('children/{child}/edit',        [ChildrenController::class, 'edit'])->name('children.edit');
    Route::patch('children/{child}',           [ChildrenController::class, 'update'])->name('children.update');
    Route::delete('children/{child}',          [ChildrenController::class, 'destroy'])->name('children.destroy');
});
// p-yoyaku 連携: 児童とp-yoyaku利用者の紐付け（アカウント発行を伴うため管理者のみ）
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('children/yoyaku-link',        [ChildrenYoyakuLinkController::class, 'index'])->name('children.yoyaku-link');
    Route::post('children/yoyaku-link/create',[ChildrenYoyakuLinkController::class, 'createAccounts'])->name('children.yoyaku-link.create');
    Route::post('children/yoyaku-link/csv',   [ChildrenYoyakuLinkController::class, 'linkCsv'])->name('children.yoyaku-link.csv');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('children',              [ChildrenController::class, 'index'])->name('children.index');
    Route::get('children/{child}',      [ChildrenController::class, 'show'])->name('children.show');
});

// 受給者証管理（leader以上）
Route::resource('children.certificates', RecipientCertificateController::class)
    ->only(['create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified', 'role:leader-or-above']);

// 利用曜日管理（leader以上）
Route::resource('children.schedules', ChildScheduleController::class)
    ->only(['create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified', 'role:leader-or-above']);

// 学校マスタ（leader以上）
Route::resource('schools', SchoolController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified', 'role:leader-or-above']);

// 他社事業所マスタ（leader以上）
Route::resource('external-facilities', ExternalFacilityController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->parameters(['external-facilities' => 'external_facility'])
    ->middleware(['auth', 'verified', 'role:leader-or-above']);

// 療育プログラムマスタ（全ロール）
Route::resource('programs', ProgramController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// プログラム項目（全ロール）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('programs/{program}/items',   [ProgramItemController::class, 'store'])   ->name('program-items.store');
    Route::delete('program-items/{programItem}', [ProgramItemController::class, 'destroy'])->name('program-items.destroy');
});

// 療育進度管理
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('program-progress',        [ProgramProgressController::class, 'index'])  ->name('program-progress.index');
    Route::post('program-progress/update',[ProgramProgressController::class, 'update']) ->name('program-progress.update');
});

// 空き枠調整
Route::get('vacancy-adjustment', [VacancyAdjustmentController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('vacancy-adjustment.index');

// 出席管理（日付ベース）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('usage-records',            [UsageRecordController::class, 'index'])     ->name('usage-records.index');
    Route::post('usage-records/bulk-store',[UsageRecordController::class, 'bulkStore']) ->name('usage-records.bulk-store');
});

// 連絡帳
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('contact-notes',                        [\App\Http\Controllers\ContactNoteController::class, 'index'])  ->name('contact-notes.index');
    Route::post('contact-notes/{contactNote}/publish', [\App\Http\Controllers\ContactNoteController::class, 'publish'])->name('contact-notes.publish');
});

// 連絡帳の年間PDF出力（要配慮個人情報の一括出力のためリーダー以上に限定）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('contact-notes/export-yearly',     [\App\Http\Controllers\ContactNoteController::class, 'exportYearly'])   ->name('contact-notes.export-yearly');
    Route::get('contact-notes/export-yearly-zip', [\App\Http\Controllers\ContactNoteController::class, 'exportYearlyZip'])->name('contact-notes.export-yearly-zip');
});

// 支援記録
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('support-records/create',          [SupportRecordController::class, 'create']) ->name('support-records.create');
    Route::post('support-records',                [SupportRecordController::class, 'store'])  ->name('support-records.store');
    Route::get('support-records/{supportRecord}', [SupportRecordController::class, 'show'])   ->name('support-records.show');
    Route::get('support-records/{supportRecord}/edit',   [SupportRecordController::class, 'edit'])   ->name('support-records.edit');
    Route::patch('support-records/{supportRecord}',      [SupportRecordController::class, 'update']) ->name('support-records.update');
});

// 問い合わせ管理（全ロール）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inquiries',                 [InquiryController::class, 'index'])->name('inquiries.index');
    Route::get('inquiries/create',          [InquiryController::class, 'create'])->name('inquiries.create');
    Route::post('inquiries',                [InquiryController::class, 'store'])->name('inquiries.store');
    Route::get('inquiries/{inquiry}',       [InquiryController::class, 'show'])->name('inquiries.show');
    Route::get('inquiries/{inquiry}/edit',  [InquiryController::class, 'edit'])->name('inquiries.edit');
    Route::patch('inquiries/{inquiry}',     [InquiryController::class, 'update'])->name('inquiries.update');
    Route::delete('inquiries/{inquiry}',    [InquiryController::class, 'destroy'])->name('inquiries.destroy');
});

// モニタリング記録（閲覧: 全員、編集: leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('children/{child}/monitoring/create',              [MonitoringRecordController::class, 'create'])->name('children.monitoring.create');
    Route::post('children/{child}/monitoring',                    [MonitoringRecordController::class, 'store'])->name('children.monitoring.store');
    Route::get('children/{child}/monitoring/{monitoringRecord}/edit',   [MonitoringRecordController::class, 'edit'])->name('children.monitoring.edit');
    Route::patch('children/{child}/monitoring/{monitoringRecord}',      [MonitoringRecordController::class, 'update'])->name('children.monitoring.update');
    Route::delete('children/{child}/monitoring/{monitoringRecord}',     [MonitoringRecordController::class, 'destroy'])->name('children.monitoring.destroy');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('children/{child}/monitoring/{monitoringRecord}', [MonitoringRecordController::class, 'show'])->name('children.monitoring.show');
    Route::get('children/{child}/monitoring/{monitoringRecord}/pdf', [MonitoringRecordController::class, 'pdf'])->name('children.monitoring.pdf');
});

// アセスメント記録（閲覧: 全員、編集: leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('children/{child}/assessments/create',               [AssessmentController::class, 'create'])->name('children.assessments.create');
    Route::post('children/{child}/assessments',                     [AssessmentController::class, 'store'])->name('children.assessments.store');
    Route::get('children/{child}/assessments/{assessment}/edit',    [AssessmentController::class, 'edit'])->name('children.assessments.edit');
    Route::patch('children/{child}/assessments/{assessment}',       [AssessmentController::class, 'update'])->name('children.assessments.update');
    Route::delete('children/{child}/assessments/{assessment}',      [AssessmentController::class, 'destroy'])->name('children.assessments.destroy');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('children/{child}/assessments/{assessment}', [AssessmentController::class, 'show'])->name('children.assessments.show');
});

// 個別支援計画（閲覧: 全員、編集: leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('children/{child}/support-plans/create',                    [SupportPlanController::class, 'create'])->name('children.support-plans.create');
    Route::post('children/{child}/support-plans',                          [SupportPlanController::class, 'store'])->name('children.support-plans.store');
    Route::get('children/{child}/support-plans/{support_plan}/edit',       [SupportPlanController::class, 'edit'])->name('children.support-plans.edit');
    Route::patch('children/{child}/support-plans/{support_plan}',          [SupportPlanController::class, 'update'])->name('children.support-plans.update');
    Route::delete('children/{child}/support-plans/{support_plan}',         [SupportPlanController::class, 'destroy'])->name('children.support-plans.destroy');
    // 計画プロセス（承認・担当者会議・同意/交付）
    Route::post('children/{child}/support-plans/{support_plan}/approve',   [SupportPlanController::class, 'approve'])->name('children.support-plans.approve');
    Route::post('children/{child}/support-plans/{support_plan}/meetings',  [SupportPlanController::class, 'storeMeeting'])->name('children.support-plans.meetings.store');
    Route::delete('children/{child}/support-plans/{support_plan}/meetings/{meeting}', [SupportPlanController::class, 'destroyMeeting'])->name('children.support-plans.meetings.destroy');
    Route::post('children/{child}/support-plans/{support_plan}/consents',  [SupportPlanController::class, 'storeConsent'])->name('children.support-plans.consents.store');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('children/{child}/support-plans/{support_plan}', [SupportPlanController::class, 'show'])->name('children.support-plans.show');
    Route::get('children/{child}/support-plans/{support_plan}/pdf', [SupportPlanController::class, 'pdf'])->name('children.support-plans.pdf');
});

// ── 請求管理（leader以上） ──────────────────
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->prefix('billing')->group(function () {
    // 月次請求（一覧・計算）
    Route::get('/',                          [BillingPeriodController::class, 'index'])    ->name('billing.index');
    Route::get('/previous-month',            [BillingPeriodController::class, 'previousMonth'])->name('billing.previous-month');
    Route::post('/calculate',                [BillingPeriodController::class, 'calculate'])->name('billing.calculate');

    // 児童別明細
    Route::get('/details/{billingDetail}',      [BillingDetailController::class, 'show'])   ->name('billing.details.show');
    Route::get('/details/{billingDetail}/edit',  [BillingDetailController::class, 'edit'])   ->name('billing.details.edit');
    Route::patch('/details/{billingDetail}',     [BillingDetailController::class, 'update']) ->name('billing.details.update');
    Route::get('/details/{billingDetail}/performance-pdf', [BillingPeriodController::class, 'performancePdf'])->name('billing.details.performance-pdf');
    Route::get('/details/{billingDetail}/proxy-receipt-pdf', [BillingPeriodController::class, 'proxyReceiptPdf'])->name('billing.details.proxy-receipt-pdf');

    // 実績記録票
    Route::get('/daily-records',             [DailyServiceRecordController::class, 'index'])     ->name('billing.daily-records.index');
    Route::post('/daily-records/bulk-update', [DailyServiceRecordController::class, 'bulkUpdate'])->name('billing.daily-records.bulk-update');

    // 上限管理
    Route::get('/cap-management',              [CopaymentCapController::class, 'index'])    ->name('billing.cap-management.index');
    Route::post('/cap-management/calculate',   [CopaymentCapController::class, 'calculate'])->name('billing.cap-management.calculate');
    Route::get('/cap-management/export',       [CopaymentCapController::class, 'export'])   ->name('billing.cap-management.export');
    Route::get('/cap-management/pdf-bundle',   [CopaymentCapController::class, 'pdfBundle'])->name('billing.cap-management.pdf-bundle');
    Route::get('/cap-management/payment-list', [CopaymentCapController::class, 'paymentListPdf'])->name('billing.cap-management.payment-list');
    Route::get('/cap-management/{copaymentCapManagement}', [CopaymentCapController::class, 'show'])->name('billing.cap-management.show');
    Route::get('/cap-management/{copaymentCapManagement}/pdf', [CopaymentCapController::class, 'pdf'])->name('billing.cap-management.pdf');
    Route::patch('/cap-management/{copaymentCapManagement}/details/{copaymentCapDetail}', [CopaymentCapController::class, 'updateExternalDetail'])->name('billing.cap-management.details.update');
    Route::post('/cap-management/{copaymentCapManagement}/transition', [CopaymentCapController::class, 'transition'])->name('billing.cap-management.transition');
    Route::patch('/cap-management/{copaymentCapManagement}/attributes', [CopaymentCapController::class, 'updateAttributes'])->name('billing.cap-management.attributes');

    // 利用者請求
    Route::get('/invoices',                       [GuardianInvoiceController::class, 'index'])        ->name('billing.invoices.index');
    Route::post('/invoices/generate',             [GuardianInvoiceController::class, 'generate'])     ->name('billing.invoices.generate');
    Route::get('/invoices/{guardianInvoice}',     [GuardianInvoiceController::class, 'show'])         ->name('billing.invoices.show');
    Route::get('/invoices/{guardianInvoice}/pdf', [GuardianInvoiceController::class, 'downloadPdf'])  ->name('billing.invoices.pdf');
    Route::get('/invoices/{guardianInvoice}/receipt', [GuardianInvoiceController::class, 'receiptPdf'])->name('billing.invoices.receipt-pdf');
    Route::patch('/invoices/{guardianInvoice}/payment', [GuardianInvoiceController::class, 'updatePayment'])->name('billing.invoices.update-payment');

    // 過誤申立
    Route::get('/error-claims',                         [ErrorClaimController::class, 'index'])  ->name('billing.error-claims.index');
    Route::get('/error-claims/create/{billingDetail}',  [ErrorClaimController::class, 'create']) ->name('billing.error-claims.create');
    Route::post('/error-claims',                        [ErrorClaimController::class, 'store'])  ->name('billing.error-claims.store');
    Route::get('/error-claims/export',                  [ErrorClaimController::class, 'export']) ->name('billing.error-claims.export');

    // 返戻管理
    Route::get('/returns',                    [ClaimReturnController::class, 'index'])    ->name('billing.returns.index');
    Route::post('/returns',                   [ClaimReturnController::class, 'store'])    ->name('billing.returns.store');
    Route::post('/returns/import',            [ClaimReturnController::class, 'import'])   ->name('billing.returns.import');
    Route::post('/returns/{claimReturn}/resubmit', [ClaimReturnController::class, 'resubmit'])->name('billing.returns.resubmit');
    Route::patch('/returns/{claimReturn}',         [ClaimReturnController::class, 'update'])    ->name('billing.returns.update');
    Route::post('/returns/{claimReturn}/transition', [ClaimReturnController::class, 'transition'])->name('billing.returns.transition');

    // 加算・減算設定
    Route::get('/expenses',                   [MonthlyExpenseController::class, 'index'])  ->name('billing.expenses.index');
    Route::post('/expenses',                  [MonthlyExpenseController::class, 'upsert']) ->name('billing.expenses.upsert');
    Route::get('/settings/service-codes',      [FacilityServiceSettingController::class, 'index'])     ->name('billing.settings.service-codes');
    Route::post('/settings/service-codes',     [FacilityServiceSettingController::class, 'bulkUpdate'])->name('billing.settings.service-codes.update');
    Route::post('/settings/service-codes/import', [FacilityServiceSettingController::class, 'importMaster'])->name('billing.settings.service-codes.import');

    // 複式CSV出力（履歴）
    Route::get('/exports/{billingExport}/download',   [BillingPeriodController::class, 'downloadExport'])->name('billing.exports.download');
    Route::post('/exports/{billingExport}/mark-submitted', [BillingPeriodController::class, 'markSubmitted'])->name('billing.exports.mark-submitted');

    // 月次請求 詳細（ワイルドカードなので最後に配置）
    Route::get('/{billingPeriod}',           [BillingPeriodController::class, 'show'])              ->name('billing.show');
    Route::patch('/{billingPeriod}/confirm', [BillingPeriodController::class, 'confirm'])           ->name('billing.confirm');
    Route::patch('/{billingPeriod}/payment-decision', [BillingPeriodController::class, 'recordPaymentDecision'])->name('billing.payment-decision');
    Route::get('/{billingPeriod}/export',    [BillingPeriodController::class, 'export'])            ->name('billing.export');
    Route::get('/{billingPeriod}/export-performance', [BillingPeriodController::class, 'exportPerformance'])->name('billing.export-performance');
    Route::get('/{billingPeriod}/validate-export',    [BillingPeriodController::class, 'validateExport'])   ->name('billing.validate-export');
    Route::get('/{billingPeriod}/export-bundle',      [BillingPeriodController::class, 'exportBundle'])     ->name('billing.export-bundle');
    Route::get('/{billingPeriod}/performance-pdf-bundle', [BillingPeriodController::class, 'performancePdfBundle'])->name('billing.performance-pdf-bundle');
    Route::get('/{billingPeriod}/proxy-receipt-bundle',   [BillingPeriodController::class, 'proxyReceiptPdfBundle'])->name('billing.proxy-receipt-bundle');
});

// 運営記録（委員会・研修/身体拘束/BCP・安全計画/自己評価。leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->prefix('operation-records')->group(function () {
    Route::get('/',                 [OperationRecordController::class, 'index'])->name('operation-records.index');
    Route::post('/committees',      [OperationRecordController::class, 'storeCommittee'])->name('operation-records.committees.store');
    Route::post('/restraints',      [OperationRecordController::class, 'storeRestraint'])->name('operation-records.restraints.store');
    Route::patch('/restraints/{restraint}/notified', [OperationRecordController::class, 'markRestraintNotified'])->name('operation-records.restraints.notified');
    Route::post('/bcp',             [OperationRecordController::class, 'upsertBcp'])->name('operation-records.bcp.upsert');
    Route::post('/safety-plan',     [OperationRecordController::class, 'upsertSafetyPlan'])->name('operation-records.safety-plan.upsert');
    Route::post('/self-evaluation', [OperationRecordController::class, 'upsertSelfEvaluation'])->name('operation-records.self-evaluation.upsert');
});

// AI下書き生成（計画・モニタリングは作成権限のある leader 以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::post('ai-draft/support-plan/{child}', [AiDraftController::class, 'supportPlan'])->name('ai-draft.support-plan');
    Route::post('ai-draft/monitoring/{child}',   [AiDraftController::class, 'monitoring'])  ->name('ai-draft.monitoring');
});

// 連絡帳のAI下書きは支援記録フォームから全スタッフが使う（保護者同意チェックはコントローラ側）
Route::post('ai-draft/contact-note/{child}', [AiDraftController::class, 'contactNote'])
    ->middleware(['auth', 'verified'])
    ->name('ai-draft.contact-note');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'stripePublicKey' => config('services.stripe.pb_key'),
    ]);
});

// ── Stripe Subscription ──────────────────
Route::post('/create-checkout-session', [StripeController::class, 'createSession'])
    ->name('stripe.create-checkout-session');

Route::get('/success', [StripeController::class, 'success'])
    ->name('stripe.success');

Route::get('/cancel', [StripeController::class, 'cancel'])
    ->name('stripe.cancel');

Route::get('/subscribe/create/{session_id}', [SubscribeController::class, 'create'])
    ->name('subscribe.create');

Route::post('/subscribe/store', [SubscribeController::class, 'store'])
    ->name('subscribe.store');

Route::get('/subscribe/done', function (\Illuminate\Http\Request $request) {
    return Inertia::render('Subscribe/Done', [
        'type'    => $request->query('type'),
        'message' => $request->query('message'),
        'email'   => $request->query('email'),
    ]);
})->name('subscribe.done');

Route::post('/subscribe/resend-reset-link', [SubscribeController::class, 'resendResetLink'])
    ->name('subscribe.resendResetLink');

// Stripe Webhook（CSRF除外済み）
Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook']);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
