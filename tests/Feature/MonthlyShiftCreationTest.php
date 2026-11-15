<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\MonthlyShift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyShiftCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_editor_after_creating_a_monthly_shift(): void
    {
        $facility = Facility::create([
            'name' => 'Shift test facility',
            'billing_type' => 'free',
            'is_active' => true,
        ]);
        $admin = User::factory()->create();
        Staff::create([
            'user_id' => $admin->id,
            'facility_id' => $facility->id,
            'name' => 'Shift test admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $created = $this->post(route('shifts.create'), ['year' => 2026, 'month' => 5]);
        $shift = MonthlyShift::where('facility_id', $facility->id)
            ->where('year', 2026)->where('month', 5)->sole();

        $created->assertRedirect(route('shifts.edit', $shift));
        $this->get(route('shifts.edit', $shift))->assertOk();
    }
}
