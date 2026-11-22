<?php

namespace Tests\Feature;

use App\Models\{BillingPeriod,Child,Facility,RecipientCertificate,Staff,UsageRecord,User};
use App\Services\{ChildServiceType,SchoolDayCalendar};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolCalendarTest extends TestCase
{
    use RefreshDatabase;
    private Facility $facility;
    private Child $child;
    private Child $foreign;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facility=Facility::create(['name'=>'Synthetic calendar','capacity_per_day'=>10,'service_type'=>'both']);
        $other=Facility::create(['name'=>'Synthetic foreign','capacity_per_day'=>10]);
        $this->child=Child::create(['facility_id'=>$this->facility->id,'name'=>'Synthetic pupil','contract_status'=>'active']);
        $this->foreign=Child::create(['facility_id'=>$other->id,'name'=>'Foreign pupil','contract_status'=>'active']);
        $this->certificate($this->child);
        $this->admin=$this->user('admin');$this->actingAs($this->admin);
    }

    private function user(string $role): User
    {
        $user=User::factory()->create();
        Staff::create(['facility_id'=>$this->facility->id,'user_id'=>$user->id,'name'=>'Synthetic '.$role,'role'=>$role,'is_active'=>true]);return $user;
    }

    private function certificate(Child $child,string $type='houday',array $extra=[]): RecipientCertificate
    {
        return RecipientCertificate::create(array_merge(['child_id'=>$child->id,'certificate_number'=>'1234567890','status'=>'active',
            'service_type'=>$type,'valid_from'=>'2026-01-01','valid_to'=>'2026-12-31'],$extra));
    }

    private function cell(?bool $value=false,string $date='2026-07-01',?int $id=null): array
    {
        return ['child_id'=>$id??$this->child->id,'date'=>$date,'value'=>$value];
    }

    private function save(array $cells,int $revision=0)
    {
        return $this->putJson('/school-calendar',['month'=>'2026-07','revision'=>$revision,'cells'=>$cells]);
    }

    public function test_admin_and_leader_can_review_month_but_staff_cannot_edit_or_open_it(): void
    {
        $this->get('/school-calendar?month=2026-07')->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('SchoolCalendar/Index')->has('days',31)->has('rows',1)->where('rows.0.name',$this->child->name)
            ->where('rows.0.values.2026-07-01',null)->where('rows.0.services.2026-07-01.type','houday')->where('revision',0));
        $this->actingAs($this->user('leader'))->get('/school-calendar?month=2026-07')->assertOk();
        $this->actingAs($this->user('staff'))->get('/school-calendar?month=2026-07')->assertForbidden();
        $this->save([$this->cell()])->assertForbidden();$this->assertDatabaseCount('child_school_days',0);
    }

    public function test_calendar_is_saved_before_attendance_and_used_when_staff_records_attendance(): void
    {
        $this->save([$this->cell()])->assertOk()->assertJson(['revision'=>1]);
        $this->assertDatabaseCount('usage_records',0);
        $this->actingAs($this->user('staff'))->postJson('/usage-records/bulk-store',['date'=>'2026-07-01','records'=>[
            ['child_id'=>$this->child->id,'status'=>'attended','is_school_day'=>true,'service_type'=>'jidou']]])->assertOk();
        $record=UsageRecord::sole();$this->assertFalse($record->is_school_day);$this->assertSame('houday',$record->service_type);
        $this->get('/usage-records?date=2026-07-01')->assertInertia(fn(Assert $page)=>$page
            ->where('rows.0.school_day_label','休業日')->where('rows.0.service_label','放課後等デイサービス'));
    }

    public function test_changing_calendar_preserves_attendance_body_and_stale_forms_cannot_revert_it(): void
    {
        $record=UsageRecord::create(['facility_id'=>$this->facility->id,'child_id'=>$this->child->id,'date'=>'2026-07-01','status'=>'absent','memo'=>'retain me','is_school_day'=>true]);
        $this->save([$this->cell()])->assertOk();
        $this->assertSame('absent',$record->fresh()->status);$this->assertSame('retain me',$record->fresh()->memo);$this->assertFalse($record->fresh()->is_school_day);
        $this->postJson('/usage-records/bulk-store',['date'=>'2026-07-01','records'=>[
            ['child_id'=>$this->child->id,'status'=>'absent','memo'=>'retain me','is_school_day'=>true,'service_type'=>'jidou']]])->assertOk();
        $this->assertFalse($record->fresh()->is_school_day);$this->assertSame('houday',$record->fresh()->service_type);
        $this->save([$this->cell(null)],1)->assertOk();$this->assertNull(SchoolDayCalendar::value($this->facility->id,$this->child->id,'2026-07-01'));
    }

    public function test_foreign_child_bad_dates_duplicates_and_invalid_values_are_atomic(): void
    {
        foreach([[$this->cell(),$this->cell(false,'2026-07-02',$this->foreign->id)],[$this->cell(),$this->cell(false,'2026-08-01')],
                 [$this->cell(),$this->cell(true)],[$this->cell(),['child_id'=>$this->child->id,'date'=>'2026-07-02','value'=>'bad']]] as $cells){
            $this->save($cells)->assertStatus(422);$this->assertDatabaseCount('child_school_days',0);
        }
    }

    public function test_stale_revision_does_not_overwrite_another_managers_changes(): void
    {
        $this->save([$this->cell()])->assertOk();$this->save([$this->cell(true)],0)->assertStatus(409);
        $this->assertFalse(SchoolDayCalendar::value($this->facility->id,$this->child->id,'2026-07-01'));
        $this->assertSame(1,(int)DB::table('school_calendar_months')->value('revision'));
    }

    public function test_confirmed_billing_month_cannot_be_changed(): void
    {
        BillingPeriod::create(['facility_id'=>$this->facility->id,'year_month'=>'2026-07','status'=>'confirmed']);
        $this->save([$this->cell()])->assertStatus(423);$this->assertDatabaseCount('child_school_days',0);
        $this->get('/school-calendar?month=2026-07')->assertInertia(fn(Assert $page)=>$page->where('locked',true));
    }

    public function test_service_type_comes_from_effective_certificate_not_facility_age_or_attendance(): void
    {
        $cert=$this->child->recipientCertificates()->sole();$cert->update(['valid_to'=>'2026-07-15']);
        $this->certificate($this->child,'jidou',['valid_from'=>'2026-07-16']);
        $this->assertSame('houday',ChildServiceType::forDate($this->facility->id,$this->child->id,'2026-07-15')['type']);
        $this->assertSame('jidou',ChildServiceType::forDate($this->facility->id,$this->child->id,'2026-07-16')['type']);
        $this->save([$this->cell(false,'2026-07-16')])->assertStatus(422);
        $this->assertNull(ChildServiceType::forDate($this->facility->id,$this->foreign->id,'2026-07-01')['type']);
    }

    public function test_missing_or_ambiguous_certificate_does_not_guess_from_facility(): void
    {
        $this->child->recipientCertificates()->update(['service_type'=>null]);
        $this->assertNull(ChildServiceType::forDate($this->facility->id,$this->child->id,'2026-07-01')['type']);
        $this->save([$this->cell()])->assertStatus(422);
        $this->child->recipientCertificates()->update(['service_type'=>'houday']);$this->certificate($this->child);
        $this->assertStringContainsString('重複',ChildServiceType::forDate($this->facility->id,$this->child->id,'2026-07-01')['reason']);
        $this->save([$this->cell()])->assertStatus(422);
    }

    public function test_legacy_attendance_flag_is_not_treated_as_confirmed_for_billing(): void
    {
        $record=UsageRecord::create(['facility_id'=>$this->facility->id,'child_id'=>$this->child->id,'date'=>'2026-07-01','status'=>'attended','is_school_day'=>true]);
        try {app(\App\Services\Billing\BillingCompletenessGuard::class)->resolve($record,$this->facility,'houday','2026-07');$this->fail('Unconfirmed school day must block billing');}
        catch(\Illuminate\Validation\ValidationException $e){$this->assertStringContainsString('school_day_unconfirmed',json_encode($e->errors()));}
    }
    public function test_month_end_historical_attendance_still_includes_the_child(): void
    {
        $this->child->update(['contract_status'=>'ended']);
        UsageRecord::create(['facility_id'=>$this->facility->id,'child_id'=>$this->child->id,'date'=>'2026-07-31','status'=>'attended']);
        $this->get('/school-calendar?month=2026-07')->assertInertia(fn(Assert $page)=>$page->has('rows',1)->where('rows.0.id',$this->child->id));
    }

    public function test_child_development_billing_does_not_require_a_school_calendar_entry(): void
    {
        $this->child->recipientCertificates()->update(['service_type'=>'jidou']);
        $this->child->supportPlans()->create(['plan_date'=>'2026-01-01','planned_duration_minutes'=>120]);
        $record=UsageRecord::create(['facility_id'=>$this->facility->id,'child_id'=>$this->child->id,'date'=>'2026-07-01','status'=>'attended','is_school_day'=>true]);
        $code=\App\Models\ServiceCodeMaster::create(['service_type'=>'jidou','service_code'=>'synthetic','service_name'=>'Synthetic child development','unit_count'=>100,'unit_type'=>'per_day','category'=>'base','valid_from'=>'2026-01-01','revision_date'=>'2026-01-01']);
        $this->mock(\App\Services\Billing\ServiceCodeResolver::class,fn($mock)=>$mock->shouldReceive('resolveBaseCode')->once()->with('jidou',true,null,null,'2026-07',10,120)->andReturn($code));
        $this->assertSame($code,app(\App\Services\Billing\BillingCompletenessGuard::class)->resolve($record,$this->facility,'jidou','2026-07'));
    }

}
