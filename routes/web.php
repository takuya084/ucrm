<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InertiaTestController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\RecipientCertificateController;
use App\Http\Controllers\ChildScheduleController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\UsageRecordController;
use App\Http\Controllers\SupportRecordController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\MonitoringRecordController;
use App\Http\Controllers\SupportPlanController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ProgramItemController;
use App\Http\Controllers\VacancyAdjustmentController;
use App\Http\Controllers\ProgramProgressController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\AiDraftController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SubscribeController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\StaffController;

Route::get('analysis', [AnalysisController::class, 'index'])->name('analysis');

// 施設設定（管理者のみ）
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('facility/edit',  [FacilityController::class, 'edit'])  ->name('facility.edit');
    Route::patch('facility',     [FacilityController::class, 'update'])->name('facility.update');
});

// 職員管理（管理者のみ）
Route::resource('staff', StaffController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified', 'role:admin']);

// 利用児童管理（閲覧: 全員、編集: leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('children/create',              [ChildrenController::class, 'create'])->name('children.create');
    Route::post('children',                    [ChildrenController::class, 'store'])->name('children.store');
    Route::get('children/{child}/edit',        [ChildrenController::class, 'edit'])->name('children.edit');
    Route::patch('children/{child}',           [ChildrenController::class, 'update'])->name('children.update');
    Route::delete('children/{child}',          [ChildrenController::class, 'destroy'])->name('children.destroy');
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
});

// 個別支援計画（閲覧: 全員、編集: leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::get('children/{child}/support-plans/create',                    [SupportPlanController::class, 'create'])->name('children.support-plans.create');
    Route::post('children/{child}/support-plans',                          [SupportPlanController::class, 'store'])->name('children.support-plans.store');
    Route::get('children/{child}/support-plans/{support_plan}/edit',       [SupportPlanController::class, 'edit'])->name('children.support-plans.edit');
    Route::patch('children/{child}/support-plans/{support_plan}',          [SupportPlanController::class, 'update'])->name('children.support-plans.update');
    Route::delete('children/{child}/support-plans/{support_plan}',         [SupportPlanController::class, 'destroy'])->name('children.support-plans.destroy');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('children/{child}/support-plans/{support_plan}', [SupportPlanController::class, 'show'])->name('children.support-plans.show');
});

// AI下書き生成（leader以上）
Route::middleware(['auth', 'verified', 'role:leader-or-above'])->group(function () {
    Route::post('ai-draft/support-plan/{child}', [AiDraftController::class, 'supportPlan'])->name('ai-draft.support-plan');
    Route::post('ai-draft/monitoring/{child}',   [AiDraftController::class, 'monitoring'])  ->name('ai-draft.monitoring');
});

Route::resource('items', ItemController::class)
->middleware(['auth', 'verified']);

Route::resource('customers', CustomerController::class)
->middleware(['auth', 'verified']);

Route::resource('purchases', PurchaseController::class)
->middleware(['auth', 'verified']);


Route::get('/inertia-test', function () {
    return Inertia::render('InertiaTest');
    }
);

Route::get('/component-test', function () {
    return Inertia::render('ComponentTest');
    }
);


Route::get('/inertia/index', [InertiaTestController::class, 'index'])->name('inertia.index');
Route::get('/inertia/create', [InertiaTestController::class, 'create'])->name('inertia.create');
Route::post('/inertia', [InertiaTestController::class, 'store'])->name('inertia.store');
Route::get('/inertia/show/{id}', [InertiaTestController::class, 'show'])->name('inertia.show');
Route::delete('/inertia/{id}', [InertiaTestController::class, 'delete'])->name('inertia.delete');



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
