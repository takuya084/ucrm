<?php

namespace Tests\Feature\Billing;

use App\Models\Child;
use App\Models\Facility;
use App\Models\ServiceCodeMaster;
use App\Models\SupportPlan;
use App\Models\UsageRecord;
use App\Services\Billing\BillingCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolated investigation only: no production calculation or master changes.
 * 990002 is a synthetic test code, NOT a submission code.
 * 609 units: CFA 2024-04-01 overview, printed p10 / PDF p12,
 * ordinary houday, capacity <=10, planned support >90 and <=180 minutes.
 * https://www.cfa.go.jp/assets/contents/node/basic_page/field_ref_resources/253aba4f-3ce0-4aa1-a777-3d42440f1ca2/25400d3f/20240412_policies_shougaijishien_shisaku_hoshukaitei_45.pdf
 * Reject incomplete/ambiguous base calculations; this is not statutory certification.
 */
class PreflightMay2026BillingTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;
    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->facility = Facility::create([
            'name' => 'Synthetic billing investigation', 'facility_code' => '9999999999',
            'service_type' => 'houday', 'area_unit_price' => 10, 'capacity_per_day' => 10,
        ]);
        $this->child = Child::create([
            'facility_id' => $this->facility->id, 'name' => 'Synthetic child',
            'contract_status' => 'active',
        ]);
        $this->child->recipientCertificates()->create([
            'certificate_number' => '9999999999', 'status' => 'active',
            'valid_from' => '2026-04-01', 'valid_to' => '2026-05-31',
            'monthly_limit' => 23, 'copayment_rate' => 10,
            'copayment_cap_monthly' => 4600, 'service_type' => 'houday',
        ]);
        UsageRecord::create([
            'facility_id' => $this->facility->id, 'child_id' => $this->child->id,
            'date' => '2026-05-07', 'status' => 'attended', 'billing_target' => true,
            'is_school_day' => true, 'service_type' => 'houday',
            'check_in_time' => '14:30', 'check_out_time' => '17:30',
            'pickup_done' => false, 'dropoff_done' => false,
        ]);
    }

    private function master(string $from = '2024-04-01'): void
    {
        ServiceCodeMaster::create([
            'revision_date' => $from, 'valid_from' => $from,
            'valid_to' => '2026-05-31', 'service_type' => 'houday',
            'service_code' => '990002', 'service_name' => 'Synthetic time category 2',
            'unit_count' => 609, 'unit_type' => 'per_day', 'category' => 'base',
            'conditions' => ['time_category' => 2, 'max_capacity' => 10],
        ]);
    }

    private function plan(int $minutes = 180): void
    {
        SupportPlan::create([
            'child_id' => $this->child->id, 'plan_date' => '2026-04-01',
            'valid_from' => '2026-04-01', 'valid_to' => '2026-05-31',
            'planned_duration_minutes' => $minutes,
        ]);
    }

    private function calculate(): \App\Models\BillingDetail
    {
        $period = app(BillingCalculationService::class)
            ->calculateMonthlyBilling($this->facility->id, '2026-05');
        $this->assertCount(1, $period->billingDetails);
        $detail = $period->billingDetails->first();
        $this->assertSame(1, (int) $detail->total_days);
        return $detail;
    }

    public function test_matching_master_and_planned_time_calculate_nonzero_base_reward(): void
    {
        $this->master();
        $this->plan();
        $detail = $this->calculate();
        $this->assertCount(1, $detail->billingDetailLines);
        $this->assertCount(1, $detail->dailyServiceRecords);
        $this->assertSame(609, (int) $detail->total_units);
        $this->assertSame(6090, (int) $detail->total_amount);
        $this->assertSame(609, (int) $detail->copayment_cap_applied);
        $this->assertSame(5481, (int) $detail->insurance_amount);
    }

    private function reject(string $reason, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Incomplete billing must be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = $e->errors()['billing'][0];
            $this->assertStringContainsString($reason, $message);
            $this->assertStringContainsString('2026-05-07', $message);
        }
    }

    private function noNewBilling(): void
    {
        foreach (['billing_periods', 'billing_details', 'billing_detail_lines', 'daily_service_records', 'guardian_invoices'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('usage_records', 1);
    }

    public function test_missing_code_rolls_back_new_month(): void
    {
        $this->plan();
        $this->reject('base_code_missing', fn () => $this->calculate());
        $this->noNewBilling();
    }

    public function test_actual_attendance_does_not_replace_missing_planned_time(): void
    {
        $this->master();
        $this->reject('planned_time_missing', fn () => $this->calculate());
        $this->noNewBilling();
    }

    public function test_wrong_effective_month_is_rejected(): void
    {
        $this->master();
        ServiceCodeMaster::query()->update(['valid_from' => '2026-06-01', 'valid_to' => null]);
        $this->plan();
        $this->reject('base_code_period_mismatch', fn () => $this->calculate());
        $this->noNewBilling();
    }

    public function test_mismatched_planned_time_is_rejected(): void
    {
        $this->master();
        $this->plan(90);
        $this->reject('base_code_conditions_mismatch', fn () => $this->calculate());
        $this->noNewBilling();
    }

    public function test_multiple_candidates_after_filtering_are_rejected(): void
    {
        $this->master();
        $this->plan();
        $duplicate = ServiceCodeMaster::first()->replicate();
        $duplicate->service_code = '990003';
        $duplicate->save();
        $this->reject('base_code_ambiguous', fn () => $this->calculate());
        $this->noNewBilling();
    }

    public function test_multiple_initial_candidates_with_only_one_matching_are_valid(): void
    {
        $this->master();
        $this->plan();
        $other = ServiceCodeMaster::first()->replicate();
        $other->service_code = '990003';
        $other->conditions = ['time_category' => 1, 'max_capacity' => 10];
        $other->save();
        $this->assertSame(609, (int) $this->calculate()->total_units);
    }

    private function invoice(\App\Models\BillingDetail $detail): \App\Models\GuardianInvoice
    {
        $guardian = \App\Models\Guardian::create(['name' => 'Synthetic guardian']);
        $this->child->guardians()->attach($guardian->id, ['is_primary' => true, 'priority_order' => 1]);
        return \App\Models\GuardianInvoice::create([
            'billing_detail_id' => $detail->id, 'guardian_id' => $guardian->id,
            'child_id' => $this->child->id, 'facility_id' => $this->facility->id,
            'year_month' => '2026-05', 'total_amount' => $detail->copayment_cap_applied,
            'copayment_amount' => $detail->copayment_cap_applied,
        ]);
    }

    private function loginAdmin(): void
    {
        $this->facility->update(['billing_type' => 'free', 'is_active' => true]);
        $user = \App\Models\User::factory()->create();
        \App\Models\Staff::create(['user_id' => $user->id, 'facility_id' => $this->facility->id,
            'name' => 'Synthetic admin', 'role' => 'admin', 'is_active' => true]);
        $this->actingAs($user);
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['billing_periods', 'billing_details', 'billing_detail_lines', 'daily_service_records', 'guardian_invoices', 'audit_logs'] as $table) {
            $result[$table] = \Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $result;
    }

    public function test_later_child_failure_restores_entire_existing_month_and_invoice(): void
    {
        $this->master();
        $this->plan();
        $this->child->update(['name_kana' => 'AAA']);
        $detail = $this->calculate();
        $invoice = $this->invoice($detail);
        $second = $this->child->replicate();
        $second->name_kana = 'ZZZ';
        $second->save();
        $cert = $this->child->recipientCertificates()->first()->replicate();
        $cert->child_id = $second->id;
        $cert->save();
        $record = UsageRecord::first()->replicate();
        $record->child_id = $second->id;
        $record->save(); // second child has no plan; first child's rows are already recalculated when this fails.
        $before = $this->snapshot();
        $this->reject('planned_time_missing', fn () => $this->calculate());
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($detail->id, $invoice->fresh()->billing_detail_id);
        $this->assertDatabaseCount('usage_records', 2);
    }

    public function test_new_month_late_failure_does_not_save_the_first_child(): void
    {
        $this->master();
        $this->plan();
        $this->child->update(['name_kana' => 'AAA']);
        $second = $this->child->replicate();
        $second->name_kana = 'ZZZ';
        $second->save();
        $cert = $this->child->recipientCertificates()->first()->replicate();
        $cert->child_id = $second->id;
        $cert->save();
        $record = UsageRecord::first()->replicate();
        $record->child_id = $second->id;
        $record->save();
        $before = $this->snapshot();
        $this->reject('planned_time_missing', fn () => $this->calculate());
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('billing_periods', 0);
        $this->assertDatabaseCount('billing_details', 0);
    }

    public function test_web_calculation_returns_dated_error_and_preserves_month(): void
    {
        $this->master();
        $this->loginAdmin();
        $this->from('/billing')->post('/billing/calculate', ['year_month' => '2026-05'])
            ->assertRedirect('/billing')->assertSessionHasErrors('billing');
        $this->assertStringContainsString('2026-05-07', session('errors')->first('billing'));
        $this->assertStringContainsString('planned_time_missing', session('errors')->first('billing'));
        $this->noNewBilling();
    }

    public function test_legacy_incomplete_details_cannot_confirm_generate_preview_or_download(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->master();
        $this->plan();
        $detail = $this->calculate();
        $invoice = $this->invoice($detail);
        $detail->dailyServiceRecords()->delete();
        $detail->billingDetailLines()->delete();
        $detail->update(['total_units' => 0, 'total_amount' => 0]);
        $this->loginAdmin();
        $period = $detail->billingPeriod;
        $export = \App\Models\BillingExport::create([
            'facility_id' => $this->facility->id, 'billing_period_id' => $period->id,
            'file_path' => 'billing_csv/legacy.zip', 'file_name' => 'legacy.zip',
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->put('billing_csv/legacy.zip', 'synthetic legacy export');
        $this->patchJson('/billing/'.$period->id.'/confirm')->assertUnprocessable()->assertJsonValidationErrors('billing');
        $this->assertSame('draft', $period->fresh()->status);
        $this->postJson('/billing/invoices/generate', ['year_month' => '2026-05'])->assertUnprocessable();
        foreach (['/billing/invoices/'.$invoice->id, '/billing/invoices/'.$invoice->id.'/pdf',
            '/billing/'.$period->id.'/export', '/billing/'.$period->id.'/export-bundle',
            '/billing/'.$period->id.'/export-performance', '/billing/exports/'.$export->id.'/download'] as $url) {
            $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('billing');
        }
        $this->postJson('/billing/exports/'.$export->id.'/mark-submitted')->assertUnprocessable();
        $this->assertFalse($export->fresh()->is_submitted);
        $this->assertDatabaseCount('guardian_invoices', 1);
        $this->assertNull($invoice->fresh()->pdf_path);
        $this->assertSame(['billing_csv/legacy.zip'], \Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }

    public function test_invoice_pdf_embeds_japanese_font_with_fresh_storage(): void
    {
        $this->master();
        $this->plan();
        $this->child->update(['name' => '請求確認児童']);
        $invoice = $this->invoice($this->calculate());

        $originalStorage = storage_path();
        $temporaryStorage = sys_get_temp_dir().'/ucrm-invoice-test-'.bin2hex(random_bytes(8));
        \Illuminate\Support\Facades\File::ensureDirectoryExists($temporaryStorage.'/framework/cache');

        try {
            $this->app->useStoragePath($temporaryStorage);
            $disk = \Illuminate\Support\Facades\Storage::fake('local');
            $path = app(\App\Services\Billing\GuardianInvoicePdfService::class)->generateInvoice($invoice);
            $pdf = $disk->get($path);

            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertStringContainsString('IPAexGothic', $pdf);
            $this->assertStringContainsString('/FontFile2', $pdf);
            $this->assertSame(609, (int) $invoice->fresh()->total_amount);
        } finally {
            $this->app->useStoragePath($originalStorage);
            \Illuminate\Support\Facades\File::deleteDirectory($temporaryStorage);
        }
    }

    public function test_matching_nonzero_bill_with_zero_family_copayment_can_confirm_and_generate_invoice(): void
    {
        $this->master();
        $this->plan();
        $this->child->recipientCertificates()->update(['is_free_of_charge' => true]);
        $detail = $this->calculate();
        $invoice = $this->invoice($detail);
        $this->loginAdmin();
        $this->patch('/billing/'.$detail->billing_period_id.'/confirm')->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/billing/invoices/generate', ['year_month' => '2026-05'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/billing/invoices/'.$invoice->id)->assertOk();
        $this->assertSame(0, (int) $invoice->fresh()->total_amount);
        $this->assertSame(609, (int) $detail->fresh()->total_units);
    }
}
