<?php

namespace Tests\Feature;

use App\Jobs\{PushContactNote, PushYoyakuBooking};
use App\Models\{Child, ContactNote, Facility, Program, Staff, SupportRecord, UsageRecord};
use App\Services\YoyakuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Tests\TestCase;

class OutboundIntegrationTargetTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;
    private Child $child;
    private UsageRecord $record;
    private ContactNote $note;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Http::preventStrayRequests();
        config(['services.houkago_plus.base_url' => 'https://synthetic.example.test']);
        $this->facility = Facility::create(['name' => 'Synthetic A', 'capacity_per_day' => 10,
            'yoyaku_business_id' => 77, 'yoyaku_api_token' => 'synthetic-token-a']);
        $this->child = $this->child($this->facility);
        $this->record = UsageRecord::create(['facility_id' => $this->facility->id, 'child_id' => $this->child->id,
            'date' => '2026-11-04', 'status' => 'attended', 'pickup_done' => true, 'check_in_time' => '15:00']);
        $this->note = ContactNote::create(['facility_id' => $this->facility->id, 'child_id' => $this->child->id,
            'date' => '2026-11-04', 'status' => 'published', 'published_at' => now(), 'guardian_message' => 'Public synthetic message']);
        Http::fake(['*' => Http::response(['ok' => true, 'applied' => true, 'cancellation_token' => null])]);
    }

    private function child(Facility $facility, int $userId = 501): Child
    {
        return Child::create(['facility_id' => $facility->id, 'name' => 'Synthetic child',
            'contract_status' => 'active', 'yoyaku_user_id' => $userId]);
    }

    private function jobs(): array
    {
        // Exercise the real queue serialization path; no token is stored in jobs.
        return array_map(fn ($job) => unserialize(serialize($job)), [
            new PushYoyakuBooking($this->record->id), new PushContactNote($this->note->id),
        ]);
    }

    private function reject(array $jobs): void
    {
        foreach ($jobs as $job) {
            try {
                $job->handle(app(YoyakuApiService::class));
                $this->fail('An unsafe target must fail visibly');
            } catch (\RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        Http::assertNothingSent();
        $this->assertSame(0, (int) $this->record->fresh()->yoyaku_sync_revision);
        $this->assertNull($this->note->fresh()->yoyaku_synced_at);
    }

    public function test_normal_serialized_jobs_send_to_the_original_facility_and_acknowledge(): void
    {
        $jobs = $this->jobs();
        $this->assertStringNotContainsString('synthetic-token-a', serialize($jobs));
        foreach ($jobs as $job) $job->handle(app(YoyakuApiService::class));
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer synthetic-token-a'));
            $this->assertSame(501, $request['user_id']);
        }
        $this->assertSame(1, (int) $this->record->fresh()->yoyaku_sync_revision);
        $this->assertNotNull($this->note->fresh()->yoyaku_synced_at);
    }

    public function test_changed_child_user_mapping_rejects_both_queued_jobs(): void
    {
        $jobs = $this->jobs();
        $this->child->update(['yoyaku_user_id' => 502]);
        $this->reject($jobs);
    }

    public function test_changed_business_mapping_rejects_both_queued_jobs(): void
    {
        $jobs = $this->jobs();
        $this->facility->update(['yoyaku_business_id' => 88]);
        $this->reject($jobs);
    }

    public function test_rotated_credentials_require_new_jobs(): void
    {
        $jobs = $this->jobs();
        $this->facility->update(['yoyaku_api_token' => 'synthetic-replacement-token']);
        $this->reject($jobs);
    }

    public function test_moving_source_records_to_another_facility_and_child_rejects_old_jobs(): void
    {
        $jobs = $this->jobs();
        $other = Facility::create(['name' => 'Synthetic B', 'capacity_per_day' => 10,
            'yoyaku_business_id' => 88, 'yoyaku_api_token' => 'synthetic-token-b']);
        $child = $this->child($other);
        $changes = ['facility_id' => $other->id, 'child_id' => $child->id];
        $this->record->update($changes);
        $this->note->update($changes);
        $this->reject($jobs);
    }

    public function test_date_changes_reject_old_jobs(): void
    {
        $jobs = $this->jobs();
        $this->record->update(['date' => '2026-11-05']);
        $this->note->update(['date' => '2026-11-05']);
        $this->reject($jobs);
    }

    public function test_duplicate_child_mapping_is_rejected_even_if_already_present_at_dispatch(): void
    {
        $this->child($this->facility);
        $this->reject($this->jobs());
    }

    public function test_duplicate_business_added_after_dispatch_is_rejected(): void
    {
        $jobs = $this->jobs();
        Facility::create(['name' => 'Synthetic duplicate', 'capacity_per_day' => 10, 'yoyaku_business_id' => 77]);
        $this->reject($jobs);
    }

    public function test_same_user_number_in_another_facility_and_deleted_duplicate_are_allowed(): void
    {
        $other = Facility::create(['name' => 'Synthetic B', 'capacity_per_day' => 10, 'yoyaku_business_id' => 88]);
        $this->child($other);
        $deleted = $this->child($this->facility);
        $deleted->delete();
        foreach ($this->jobs() as $job) $job->handle(app(YoyakuApiService::class));
        Http::assertSentCount(2);
    }

    public function test_deleted_child_never_receives_queued_content(): void
    {
        $jobs = $this->jobs();
        $this->child->delete();
        $this->reject($jobs);
    }

    public function test_cross_facility_child_reference_never_sends(): void
    {
        $other = Facility::create(['name' => 'Synthetic B', 'capacity_per_day' => 10, 'yoyaku_business_id' => 88]);
        $child = $this->child($other);
        $this->record->update(['child_id' => $child->id]);
        $this->note->update(['child_id' => $child->id]);
        foreach ($this->jobs() as $job) $job->handle(app(YoyakuApiService::class));
        Http::assertNothingSent();
        $this->assertSame(0, (int) $this->record->fresh()->yoyaku_sync_revision);
    }

    public function test_legacy_jobs_without_a_captured_target_fail_closed(): void
    {
        $jobs = $this->jobs();
        foreach ($jobs as $job) $job->target = null;
        $this->reject($jobs);
    }

    public function test_linking_an_initially_unlinked_child_does_not_retarget_old_jobs(): void
    {
        $this->child->update(['yoyaku_user_id' => null]);
        $jobs = $this->jobs();
        $this->child->update(['yoyaku_user_id' => 501]);
        $this->reject($jobs);
    }

    public function test_support_for_another_child_or_date_is_not_disclosed(): void
    {
        $other = $this->child($this->facility, 502);
        foreach ([['child_id' => $other->id, 'date' => '2026-11-04'],
            ['child_id' => $this->child->id, 'date' => '2026-11-05']] as $fields) {
            $support = SupportRecord::create($fields + ['condition' => 'poor']);
            $this->note->update(['support_record_id' => $support->id]);
            $this->reject([new PushContactNote($this->note->id)]);
        }
    }

    public function test_foreign_attendance_attached_to_support_is_not_disclosed(): void
    {
        $other = $this->child($this->facility, 502);
        $usage = UsageRecord::create(['facility_id' => $this->facility->id, 'child_id' => $other->id,
            'date' => '2026-11-04', 'status' => 'attended']);
        $support = SupportRecord::create(['child_id' => $this->child->id, 'date' => '2026-11-04', 'usage_record_id' => $usage->id]);
        $this->note->update(['support_record_id' => $support->id]);
        $this->reject([new PushContactNote($this->note->id)]);
    }

    public function test_foreign_program_and_publisher_are_rejected(): void
    {
        $other = Facility::create(['name' => 'Synthetic B', 'capacity_per_day' => 10]);
        $staff = Staff::create(['user_id' => \App\Models\User::factory()->create()->id, 'facility_id' => $other->id, 'name' => 'Synthetic B staff', 'role' => 'staff', 'is_active' => true]);
        $this->note->update(['published_by' => $staff->id]);
        $this->reject([new PushContactNote($this->note->id)]);
        $this->note->update(['published_by' => null]);
        $support = SupportRecord::create(['child_id' => $this->child->id, 'date' => '2026-11-04']);
        $program = Program::create(['facility_id' => $other->id, 'name' => 'Synthetic foreign activity', 'category' => 'other']);
        $support->programs()->attach($program->id, ['duration_minutes' => 5]);
        $this->note->update(['support_record_id' => $support->id]);
        $this->reject([new PushContactNote($this->note->id)]);
    }

    public function test_valid_support_sends_only_public_fields(): void
    {
        $support = SupportRecord::create(['child_id' => $this->child->id, 'date' => '2026-11-04',
            'usage_record_id' => $this->record->id, 'condition' => 'good', 'care_note' => 'PRIVATE SYNTHETIC CARE',
            'challenge_note' => 'PRIVATE SYNTHETIC CHALLENGE', 'behavior_note' => 'PRIVATE SYNTHETIC BEHAVIOR']);
        $program = Program::create(['facility_id' => $this->facility->id, 'name' => 'Synthetic public activity', 'category' => 'other']);
        $support->programs()->attach($program->id, ['duration_minutes' => 5, 'memo' => 'PRIVATE SYNTHETIC PIVOT']);
        $this->note->update(['support_record_id' => $support->id]);
        (new PushContactNote($this->note->id))->handle(app(YoyakuApiService::class));
        Http::assertSent(function ($r) {
            $this->assertSame('good', $r['condition']);
            $this->assertSame([['name' => 'Synthetic public activity', 'duration_minutes' => 5]], $r['activities']);
            $this->assertStringNotContainsString('PRIVATE SYNTHETIC', json_encode($r->data()));
            return true;
        });
    }

    public function test_draft_and_deleted_notes_are_not_sent(): void
    {
        $job = new PushContactNote($this->note->id);
        $this->note->update(['status' => 'draft']);
        $job->handle(app(YoyakuApiService::class));
        $this->note->delete();
        $job->handle(app(YoyakuApiService::class));
        Http::assertNothingSent();
    }

    public function test_note_edited_during_http_is_not_marked_synced(): void
    {
        Http::fake(function () {
            $this->note->update(['guardian_message' => 'New unsent synthetic message']);
            return Http::response(['ok' => true]);
        });
        (new PushContactNote($this->note->id))->handle(app(YoyakuApiService::class));
        $this->assertNull($this->note->fresh()->yoyaku_synced_at);
        $this->assertSame('New unsent synthetic message', $this->note->fresh()->guardian_message);
    }

    public function test_credentials_rechecked_at_http_client_creation(): void
    {
        $hash = hash('sha256', 'synthetic-token-a');
        $this->facility->update(['yoyaku_api_token' => 'synthetic-token-b']);
        $api = app(YoyakuApiService::class);
        foreach (['booking', 'note'] as $kind) {
            try {
                if ($kind === 'booking') $api->syncBooking([], $this->facility->id, $hash);
                else $api->upsertContactNote([], $this->facility->id, $hash);
                $this->fail('Must not use changed credentials');
            } catch (\RuntimeException $e) {
                $this->assertSame('Outbound integration credentials changed', $e->getMessage());
            }
        }
        Http::assertNothingSent();
    }
}
