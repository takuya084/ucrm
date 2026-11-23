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

    public function test_admin_can_create_open_and_save_a_monthly_shift_twice(): void
    {
        $facility = Facility::create([
            'name' => 'Shift test facility',
            'billing_type' => 'free',
            'is_active' => true,
        ]);
        $admin = User::factory()->create();
        $staff = Staff::create([
            'user_id' => $admin->id,
            'facility_id' => $facility->id,
            'name' => 'Shift test admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        \App\Models\ShiftLabel::create(['facility_id' => $facility->id, 'name' => '勤務', 'is_off' => false, 'display_order' => 0]);
        $this->actingAs($admin);

        $created = $this->post(route('shifts.create'), ['year' => 2026, 'month' => 5]);
        $shift = MonthlyShift::where('facility_id', $facility->id)
            ->where('year', 2026)->where('month', 5)->sole();

        $created->assertRedirect(route('shifts.edit', $shift));
        $this->get(route('shifts.edit', $shift))->assertOk();

        $newStaff = Staff::create([
            'user_id' => User::factory()->create()->id,
            'facility_id' => $facility->id,
            'name' => 'Shift test staff',
            'role' => 'staff',
            'is_active' => true,
        ]);

        $date = '2026-05-07';
        $saveUrl = route('shifts.bulk-save', $shift);
        $editUrl = route('shifts.edit', $shift);
        foreach (['13:00', '13:15'] as $time) {
            $this->from($editUrl)->post($saveUrl, [
                'entries' => [
                    ['staff_id' => $staff->id, 'date' => $date, 'start_time' => $time, 'work_type' => '勤務'],
                    ['staff_id' => $newStaff->id, 'date' => $date, 'start_time' => $time, 'work_type' => '勤務'],
                ],
                'day_notes' => [['date' => $date, 'note' => $time]],
            ])->assertSessionHasNoErrors()->assertRedirect($editUrl);

            $entries = $shift->entries()->whereDate('date', $date)->get();
            $this->assertCount(2, $entries);
            foreach ($entries as $entry) {
                $this->assertSame($time, substr($entry->start_time, 0, 5));
            }
            $this->assertSame($time, $shift->dayNotes()->whereDate('date', $date)->sole()->note);
        }

        $this->from($editUrl)->post($saveUrl, [
            'entries' => [['staff_id' => $staff->id, 'date' => $date]],
            'day_notes' => [['date' => $date, 'note' => null]],
        ])->assertSessionHasNoErrors()->assertRedirect($editUrl);
        $this->assertFalse($shift->dayNotes()->whereDate('date', $date)->exists());
    }
}
