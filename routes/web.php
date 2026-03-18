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

Route::get('analysis', [AnalysisController::class, 'index'])->name('analysis');

// 施設設定
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('facility/edit',  [FacilityController::class, 'edit'])  ->name('facility.edit');
    Route::patch('facility',     [FacilityController::class, 'update'])->name('facility.update');
});

// 利用児童管理
Route::resource('children', ChildrenController::class)
    ->middleware(['auth', 'verified']);

// 受給者証管理（children配下のネストリソース）
Route::resource('children.certificates', RecipientCertificateController::class)
    ->only(['create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// 利用曜日管理（children配下のネストリソース）
Route::resource('children.schedules', ChildScheduleController::class)
    ->only(['create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// 学校マスタ
Route::resource('schools', SchoolController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// 療育プログラムマスタ
Route::resource('programs', ProgramController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// プログラム項目（サブ項目）
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

// 問い合わせ管理
Route::resource('inquiries', InquiryController::class)
    ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// モニタリング記録（children配下のネストリソース）
Route::resource('children.monitoring', MonitoringRecordController::class)
    ->only(['create', 'store', 'show', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// 個別支援計画（children配下のネストリソース）
Route::resource('children.support-plans', SupportPlanController::class)
    ->only(['create', 'store', 'show', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);

// AI下書き生成
Route::middleware(['auth', 'verified'])->group(function () {
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
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
