<?php

namespace Tests\Feature;

use App\Models\{Child, Facility, Staff, UsageRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedTransportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'leader'): array
    {
        Queue::fake();
        config(['services.houkago_plus.base_url' => 'https://p-yoyaku.test']);
        Http::preventStrayRequests();
        $facility = Facility::create(['name' => '送迎テスト', 'facility_code' => '1310000001',
            'service_type' => 'houday', 'yoyaku_business_id' => 7, 'yoyaku_api_token' => 'facility-test-token']);
        $user = User::factory()->create();
        Staff::create(['user_id' => $user->id, 'facility_id' => $facility->id, 'name' => '職員', 'role' => $role, 'is_active' => true]);
        $child = Child::create(['facility_id' => $facility->id, 'name' => '児童', 'contract_status' => 'active', 'yoyaku_user_id' => 31]);
        UsageRecord::create(['facility_id' => $facility->id, 'child_id' => $child->id, 'date' => '2026-12-01', 'status' => 'attended']);
        $this->actingAs($user);
        return [$facility, $child];
    }

    private function snapshot(?int $staffId = 41, int $revision = 2): array
    {
        return ['business_id' => 7, 'staff' => [['id' => 41, 'name' => '送迎職員A'], ['id' => 42, 'name' => '送迎職員B']],
            'reservations' => [['id' => 51, 'user_id' => 31, 'name' => '児童',
                'pickup_time' => '14:30', 'dropoff_time' => '17:30', 'pickup_location' => 'school', 'dropoff_location' => 'home',
                'pickup_staff_id' => $staffId, 'pickup_staff_name' => $staffId ? '送迎職員A' : null,
                'dropoff_staff_id' => 42, 'dropoff_staff_name' => '送迎職員B', 'revision' => $revision]]];
    }

    private function input(): array
    {
        return ['service_date' => '2026-12-01', 'reservation_id' => 51, 'revision' => 2, 'direction' => 'pickup', 'staff_id' => 42];
    }

    public function test_shared_assignments_are_visible_after_attendance_is_saved_and_updates_use_facility_credentials(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/daily*' => Http::response($this->snapshot()),
            '*/api/transport/assignment' => Http::response(['ok' => true])]);
        $this->get('/dashboard?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transportState', 'connected')->has('transportAssignments', 2)
            ->where('transportAssignments.0.staff_name', '送迎職員A')->where('transportAssignments.1.staff_name', '送迎職員B')
            ->where('transportVisits.0.name', '児童')->has('transportStaff', 2));
        $this->get('/transport?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Transport/Index')->where('transportState', 'connected')->has('transportAssignments', 2)->has('transportStaff', 2));
        $this->put('/dashboard/transport-assignment', $this->input())->assertRedirect('/transport?date=2026-12-01');
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->hasHeader('Authorization', 'Bearer facility-test-token')
            && $request['business_id'] === 7 && $request['reservation_id'] === 51 && $request['staff_id'] === 42 && $request['revision'] === 2);
        $this->assertDatabaseCount('daily_transport_assignments', 0);
    }

    public function test_ordinary_staff_can_see_shared_assignments_but_cannot_edit_them(): void
    {
        $this->fixture('staff');
        Http::fake(['*/api/transport/daily*' => Http::response($this->snapshot(null, 3))]);
        $this->get('/dashboard?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('transportAssignments', 2)->where('transportAssignments.0.staff_name', null)
            ->has('transportStaff', 0)->where('canManage', false));
        $this->putJson('/dashboard/transport-assignment', $this->input())->assertForbidden();
        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    }

    public function test_remote_failure_and_conflicts_do_not_fall_back_to_local_success(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/daily*' => Http::response([], 503),
            '*/api/transport/assignment' => Http::response(['errors' => ['assignment' => ['競合']]], 422)]);
        $this->get('/dashboard?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transportState', 'unavailable')->has('transportAssignments', 0));
        $this->putJson('/dashboard/transport-assignment', $this->input())->assertUnprocessable()->assertJsonValidationErrors('assignment');
        $this->assertDatabaseCount('daily_transport_assignments', 0);
    }

    public function test_unassignment_is_sent_as_null_and_changed_remote_values_are_reloaded(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/daily*' => Http::response($this->snapshot(null, 3)),
            '*/api/transport/assignment' => Http::response(['ok' => true])]);
        $this->put('/dashboard/transport-assignment', array_replace($this->input(), ['staff_id' => null]))->assertRedirect();
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && array_key_exists('staff_id', $request->data()) && $request['staff_id'] === null);
        $this->get('/dashboard?date=2026-12-01')->assertInertia(fn (Assert $page) => $page
            ->where('transportAssignments.0.staff_id', null)->where('transportAssignments.0.revision', 3));
    }

    public function test_a_snapshot_for_a_different_business_is_not_displayed(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/daily*' => Http::response(array_replace($this->snapshot(), ['business_id' => 8]))]);
        $this->get('/dashboard?date=2026-12-01')->assertInertia(fn (Assert $page) => $page
            ->where('transportState', 'unavailable')->has('transportAssignments', 0));
    }
}
