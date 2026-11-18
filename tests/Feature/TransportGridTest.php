<?php

namespace Tests\Feature;

use App\Models\{Facility, Staff, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransportGridTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'leader'): void
    {
        config(['services.houkago_plus.base_url' => 'https://p-yoyaku.test']);
        Http::preventStrayRequests();
        $facility = Facility::create(['name' => '一覧', 'service_type' => 'houday', 'yoyaku_business_id' => 77, 'yoyaku_api_token' => 'own-facility']);
        $user = User::factory()->create();
        Staff::create(['user_id' => $user->id, 'facility_id' => $facility->id, 'name' => '職員', 'role' => $role, 'is_active' => true]);
        $this->actingAs($user);
    }

    private function input(): array
    {
        return ['date' => '2026-12-01', 'rows' => [['user_id' => 3, 'snapshot' => 'opaque-token', 'action' => 'save',
            'pickup_time' => '14:30', 'pickup_location' => 'school', 'pickup_staff_id' => 8,
            'dropoff_time' => null, 'dropoff_location' => null, 'dropoff_staff_id' => null]]];
    }

    public function test_batch_is_forwarded_with_own_business_and_credentials_including_all_cells(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/grid' => Http::response(['ok' => true])]);
        $this->putJson('/dashboard/transport-grid', $this->input() + ['business_id' => 999])->assertOk();
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer own-facility')
            && $request['business_id'] === 77 && $request['rows'][0]['pickup_time'] === '14:30'
            && $request['rows'][0]['snapshot'] === 'opaque-token' && $request['rows'][0]['pickup_staff_id'] === 8);
        $this->assertDatabaseCount('daily_transport_assignments', 0);
    }

    public function test_validation_errors_keep_remote_row_and_cell_positions(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/grid' => Http::response(['errors' => ['rows.0.pickup_time' => ['5分刻みで入力してください。']]], 422)]);
        $this->putJson('/dashboard/transport-grid', $this->input())->assertUnprocessable()->assertJsonValidationErrors('rows.0.pickup_time');
    }

    public function test_ordinary_staff_can_read_but_cannot_write_and_wrong_business_is_rejected(): void
    {
        $this->fixture('staff');
        Http::fake(['*/api/transport/grid*' => Http::response(['business_id' => 77, 'date' => '2026-12-01', 'rows' => [], 'staff' => [], 'times' => [], 'locations' => []])]);
        $this->getJson('/dashboard/transport-grid?date=2026-12-01')->assertOk();
        $this->putJson('/dashboard/transport-grid', $this->input())->assertForbidden();
        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    }

    public function test_unavailable_remote_does_not_save_locally(): void
    {
        $this->fixture();
        Http::fake(['*/api/transport/grid' => Http::response([], 503)]);
        $this->putJson('/dashboard/transport-grid', $this->input())->assertStatus(503);
        $this->assertDatabaseCount('daily_transport_assignments', 0);
    }
}
