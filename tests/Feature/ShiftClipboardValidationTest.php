<?php
namespace Tests\Feature;

use App\Models\{Facility, User, Staff, ShiftLabel, MonthlyShift, ShiftEntry};
use App\Support\ShiftDemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftClipboardValidationTest extends TestCase
{
    use RefreshDatabase;
    private Staff $admin;
    private MonthlyShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $f=Facility::create(['name'=>'Clipboard test','billing_type'=>'free','is_active'=>true]);
        $u=User::factory()->create();
        $this->admin=Staff::create(['user_id'=>$u->id,'facility_id'=>$f->id,'name'=>'Admin','role'=>'admin','is_active'=>true]);
        foreach (['早番','休み'] as $name) ShiftLabel::create(['facility_id'=>$f->id,'name'=>$name,'is_off'=>$name==='休み','display_order'=>0]);
        $this->shift=MonthlyShift::create(['facility_id'=>$f->id,'year'=>2026,'month'=>12,'status'=>'draft']);
        $this->actingAs($u);
    }
    private function entry(array $overrides=[]): array
    {
        return array_replace(['staff_id'=>$this->admin->id,'date'=>'2026-12-01','work_type'=>'早番','start_time'=>'09:00'],$overrides);
    }
    private function patterns(array $override=[]): array
    {
        return array_map(fn($day)=>array_replace(['day_of_week'=>$day,'work_type'=>'早番','start_time'=>'09:00'],$override),['mon','tue','wed','thu','fri','sat','sun']);
    }
    public function test_valid_time_boundaries_save_and_null_can_clear(): void
    {
        foreach (['06:00','22:45',null] as $time) $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry(['start_time'=>$time])]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($this->shift->entries()->sole()->start_time);
    }
    public function test_invalid_label_rejects_the_whole_save_and_does_not_create_good_rows(): void
    {
        $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry(),$this->entry(['date'=>'2026-12-02','work_type'=>'未登録'])]])->assertUnprocessable()->assertJsonValidationErrors('entries.1.work_type');
        $this->assertDatabaseCount('shift_entries',0);
    }
    public function test_foreign_facility_label_is_not_an_available_label(): void
    {
        $f=Facility::create(['name'=>'Other','billing_type'=>'free','is_active'=>true]);
        ShiftLabel::create(['facility_id'=>$f->id,'name'=>'他事業所のみ','is_off'=>false,'display_order'=>0]);
        $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry(['work_type'=>'他事業所のみ'])]])->assertUnprocessable();
        $this->patchJson(route('staff.work-patterns.update',$this->admin),['patterns'=>$this->patterns(['work_type'=>'他事業所のみ'])])->assertUnprocessable();
    }
    public function test_unavailable_times_rejected_in_both_forms(): void
    {
        foreach (['05:45','09:10','23:00'] as $time) {
            $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry(['start_time'=>$time])]])->assertUnprocessable()->assertJsonValidationErrors('entries.0.start_time');
            $this->patchJson(route('staff.work-patterns.update',$this->admin),['patterns'=>$this->patterns(['start_time'=>$time])])->assertUnprocessable()->assertJsonValidationErrors('patterns.0.start_time');
        }
        $this->assertDatabaseCount('staff_work_patterns',0);
        $this->assertDatabaseCount('shift_entries',0);
    }
    public function test_rows_outside_month_or_facility_and_confirmed_edits_are_rejected(): void
    {
        $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry(['date'=>'2027-01-01'])]])->assertUnprocessable();
        $f=Facility::create(['name'=>'Other','billing_type'=>'free','is_active'=>true]);
        $s=Staff::create(['user_id'=>User::factory()->create()->id,'facility_id'=>$f->id,'name'=>'Foreign','role'=>'staff','is_active'=>true]);
        $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry(['staff_id'=>$s->id])]])->assertUnprocessable();
        $this->shift->update(['status'=>'confirmed']);
        $this->postJson(route('shifts.bulk-save',$this->shift),['entries'=>[$this->entry()]])->assertForbidden();
        $this->assertDatabaseCount('shift_entries',0);
    }
    public function test_work_pattern_days_must_be_unique_and_valid_values_are_saved(): void
    {
        $this->patchJson(route('staff.work-patterns.update',$this->admin),['patterns'=>$this->patterns()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('staff_work_patterns',7);
        $this->patchJson(route('staff.work-patterns.update',$this->admin),['patterns'=>$this->patterns(['day_of_week'=>'mon'])])->assertUnprocessable();
    }
    public function test_demo_is_separate_and_existing_rows_survive_and_cannot_be_overwritten(): void
    {
        $before=$this->admin->fresh()->getAttributes();
        $result=app(ShiftDemoData::class)->create('2026-12','Very-long-fixture-password-123!');
        $this->assertNotSame($this->admin->facility_id,$result['facility_id']);
        $this->assertSame($before,$this->admin->fresh()->getAttributes());
        $this->assertSame(10,Staff::where('facility_id',$result['facility_id'])->count());
        $this->assertSame(70,\App\Models\StaffWorkPattern::where('facility_id',$result['facility_id'])->count());
        $this->assertSame(12,\App\Models\Child::where('facility_id',$result['facility_id'])->count());
        foreach ($result['shifts'] as $s) {
            $this->assertSame(310,ShiftEntry::where('monthly_shift_id',$s['id'])->count());
            $this->assertCount(4,$s['examples']);
        }
        $count=Staff::count();
        try {app(ShiftDemoData::class)->create('2026-12','Very-long-fixture-password-123!');$this->fail('must not overwrite');}
        catch (\RuntimeException $e) {$this->assertSame('demo_already_exists_no_overwrite',$e->getMessage());}
        $this->assertSame($count,Staff::count());
    }
    public function test_demo_command_refuses_non_demo_environment_before_writing(): void
    {
        $this->app->instance('env','production');
        try {app(ShiftDemoData::class)->create('2026-12','Very-long-fixture-password-123!');$this->fail('must refuse');}
        catch (\RuntimeException $e) {$this->assertSame('manual_demo_only',$e->getMessage());}
        $this->assertDatabaseCount('staff',1);
    }
}
