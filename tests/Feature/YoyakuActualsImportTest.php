<?php

namespace Tests\Feature;

use App\Models\{BillingPeriod, Child, Facility, UsageRecord};
use App\Services\{YoyakuApiService, YoyakuSyncService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Tests\TestCase;

class YoyakuActualsImportTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;
    private Child $child;
    private UsageRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Http::preventStrayRequests();
        config(['services.houkago_plus.base_url' => 'https://synthetic.example.test']);
        $this->facility = Facility::create(['name' => 'Synthetic A', 'capacity_per_day' => 10,
            'yoyaku_business_id' => 77, 'yoyaku_api_token' => 'synthetic-token']);
        $this->child = Child::create(['facility_id' => $this->facility->id, 'name' => 'Synthetic child',
            'contract_status' => 'active', 'yoyaku_user_id' => 501]);
        $this->record = UsageRecord::create(['facility_id' => $this->facility->id, 'child_id' => $this->child->id,
            'date' => '2026-11-04', 'status' => 'attended', 'pickup_done' => false, 'dropoff_done' => true,
            'check_in_time' => '14:00', 'memo' => 'Keep this memo']);
        Bus::fake();
    }

    private function row(array $extra = []): array
    {
        return $extra + ['user_id' => 501, 'date' => '2026-11-04',
            'actual_pickup_at' => '2026-11-04T05:00:00.000000Z', 'actual_dropoff_at' => null];
    }

    private function runImport(): array
    {
        return app(YoyakuSyncService::class)->importActuals($this->facility->id, '2026-11-04');
    }

    private function assertRejectedWithoutChanges(callable $callback): void
    {
        $before = $this->record->fresh()->getRawOriginal();
        $audits = \App\Models\AuditLog::count();
        try {
            $callback();
            $this->fail('Import must be rejected');
        } catch (\RuntimeException | \Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertSame($before, $this->record->fresh()->getRawOriginal());
        $this->assertSame($audits, \App\Models\AuditLog::count());
        $this->assertDatabaseCount('daily_service_records', 0);
        Bus::assertNothingDispatched();
    }

    public function test_import_updates_only_transport_flags_keeps_audit_and_does_not_push_reservations(): void
    {
        Http::fake(['*' => Http::response([$this->row()])]);
        $before = $this->record->fresh()->getRawOriginal();
        $audits = \App\Models\AuditLog::count();
        $this->assertSame(['imported' => 1, 'skipped' => 0], $this->runImport());
        $record = $this->record->fresh();
        $this->assertTrue($record->pickup_done);
        $this->assertFalse($record->dropoff_done);
        foreach (['status', 'memo', 'check_in_time', 'check_out_time', 'yoyaku_sync_revision', 'yoyaku_cancellation_token'] as $field) {
            $this->assertSame($before[$field], $record->getRawOriginal($field));
        }
        $this->assertGreaterThan($audits, \App\Models\AuditLog::count());
        $this->assertDatabaseCount('daily_service_records', 0);
        $this->assertSame(['imported' => 1, 'skipped' => 0], $this->runImport());
        Bus::assertNothingDispatched();
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r['business_id'] === 77
            && $r['date'] === '2026-11-04' && $r->hasHeader('Authorization', 'Bearer synthetic-token'));
    }

    public function test_duplicate_child_mapping_aborts_the_entire_batch(): void
    {
        $another = Child::create(['facility_id' => $this->facility->id, 'name' => 'Synthetic second',
            'contract_status' => 'active', 'yoyaku_user_id' => 502]);
        Child::create(['facility_id' => $this->facility->id, 'name' => 'Synthetic duplicate',
            'contract_status' => 'active', 'yoyaku_user_id' => 502]);
        Http::fake(['*' => Http::response([$this->row(), $this->row(['user_id' => 502])])]);
        $this->assertRejectedWithoutChanges(fn () => $this->runImport());
    }

    public function test_duplicate_business_mapping_is_rejected_before_http(): void
    {
        Facility::create(['name' => 'Synthetic duplicate', 'capacity_per_day' => 10, 'yoyaku_business_id' => 77]);
        Http::fake();
        $this->assertRejectedWithoutChanges(fn () => $this->runImport());
        Http::assertNothingSent();
    }

    public function test_same_user_number_in_another_facility_and_deleted_child_do_not_conflict(): void
    {
        $other = Facility::create(['name' => 'Synthetic B', 'capacity_per_day' => 10, 'yoyaku_business_id' => 88]);
        $otherChild = Child::create(['facility_id' => $other->id, 'name' => 'Synthetic B child',
            'contract_status' => 'active', 'yoyaku_user_id' => 501]);
        $otherRecord = UsageRecord::create(['facility_id' => $other->id, 'child_id' => $otherChild->id,
            'date' => '2026-11-04', 'status' => 'attended', 'pickup_done' => false]);
        $deleted = Child::create(['facility_id' => $this->facility->id, 'name' => 'Synthetic deleted',
            'contract_status' => 'active', 'yoyaku_user_id' => 501]);
        $deleted->delete();
        Bus::fake();
        Http::fake(['*' => Http::response([$this->row()])]);
        $this->assertSame(['imported' => 1, 'skipped' => 0], $this->runImport());
        $this->assertFalse($otherRecord->fresh()->pickup_done);
        Bus::assertNothingDispatched();
    }

    public function test_absent_cancelled_and_deleted_attendance_is_never_restored(): void
    {
        Http::fake(['*' => Http::response([$this->row()])]);
        foreach (['absent', 'absent_notice', 'cancel'] as $status) {
            $this->record->update(['status' => $status]);
            Bus::fake();
            $before = $this->record->fresh()->getRawOriginal();
            $this->assertSame(['imported' => 0, 'skipped' => 1], $this->runImport());
            $this->assertSame($before, $this->record->fresh()->getRawOriginal());
            Bus::assertNothingDispatched();
        }
        $this->record->delete();
        Bus::fake();
        $this->assertSame(['imported' => 0, 'skipped' => 1], $this->runImport());
        $this->assertTrue($this->record->fresh()->trashed());
        Bus::assertNothingDispatched();
    }

    public function test_unknown_child_or_missing_attendance_is_skipped_without_creating_records(): void
    {
        $this->record->delete();
        Http::fake(['*' => Http::response([$this->row(), $this->row(['user_id' => 999])])]);
        $this->assertSame(['imported' => 0, 'skipped' => 2], $this->runImport());
        $this->assertDatabaseCount('usage_records', 1);
        $this->assertDatabaseCount('daily_service_records', 0);
    }

    public function test_locked_and_calculating_billing_periods_reject_import(): void
    {
        Http::fake(['*' => Http::response([$this->row()])]);
        foreach (['confirmed', 'submitted', 'completed', 'calculating'] as $status) {
            BillingPeriod::updateOrCreate(['facility_id' => $this->facility->id, 'year_month' => '2026-11'], ['status' => $status]);
            $this->assertRejectedWithoutChanges(fn () => $this->runImport());
        }
    }

    public function test_malformed_wrong_date_and_duplicate_rows_never_partially_import(): void
    {
        foreach ([['error' => 'unexpected'], [$this->row(), $this->row()],
            [$this->row(), $this->row(['user_id' => 502, 'date' => '2026-11-05'])],
            [$this->row(), ['user_id' => 502, 'date' => '2026-11-04']],
            [$this->row(['actual_pickup_at' => 'invalid'])], [$this->row(['actual_pickup_at' => ''])], ['invalid'],
            [$this->row(['user_id' => 0])]] as $response) {
            Http::fake(['*' => Http::response($response)]);
            $this->assertRejectedWithoutChanges(fn () => $this->runImport());
        }
    }

    public function test_fetch_failure_and_missing_credentials_fail_visibly(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->assertRejectedWithoutChanges(fn () => $this->runImport());
        $this->facility->update(['yoyaku_api_token' => null]);
        Http::fake();
        $this->assertRejectedWithoutChanges(fn () => $this->runImport());
        Http::assertNothingSent();
    }

    public function test_empty_response_does_not_clear_existing_flags(): void
    {
        Http::fake(['*' => Http::response([])]);
        $before = $this->record->fresh()->getRawOriginal();
        $this->assertSame(['imported' => 0, 'skipped' => 0], $this->runImport());
        $this->assertSame($before, $this->record->fresh()->getRawOriginal());
    }

    public function test_changed_mapping_during_http_is_rejected(): void
    {
        Http::fake(function () {
            $this->facility->update(['yoyaku_business_id' => 88]);
            return Http::response([$this->row()]);
        });
        // Facility edits have their own audit trail; only attendance must remain unchanged.
        $before = $this->record->fresh()->getRawOriginal();
        try { $this->runImport(); $this->fail('Changed mapping must be rejected'); }
        catch (\RuntimeException $e) { $this->assertSame('Facility integration changed during import', $e->getMessage()); }
        $this->assertSame($before, $this->record->fresh()->getRawOriginal());
    }

    public function test_received_cancellation_blocks_stale_actuals_even_for_attended_records(): void
    {
        \Illuminate\Support\Facades\DB::table('yoyaku_booking_states')->insert([
            'facility_id' => $this->facility->id, 'yoyaku_user_id' => 501, 'date' => '2026-11-04',
            'revision' => 2, 'cancelled' => true, 'payload_hash' => str_repeat('a', 64),
        ]);
        Http::fake(['*' => Http::response([$this->row()])]);
        $before = $this->record->fresh()->getRawOriginal();
        $this->assertSame(['imported' => 0, 'skipped' => 1], $this->runImport());
        $this->assertSame($before, $this->record->fresh()->getRawOriginal());
        Bus::assertNothingDispatched();
    }

    public function test_invalid_command_date_fails_without_http_or_writes(): void
    {
        Http::fake();
        $this->assertRejectedWithoutChanges(fn () => app(YoyakuSyncService::class)->importActuals($this->facility->id, '2026-02-30'));
        Http::assertNothingSent();
    }
}
