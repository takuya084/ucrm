<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AbsenceRegisterTest extends TestCase {
 use RefreshDatabase;
 private function fixture(): array { $f=\App\Models\Facility::create(['name'=>'確認事業所','facility_code'=>(string)random_int(1000000000,1999999999),'service_type'=>'houday']);$user=\App\Models\User::factory()->create();\App\Models\Staff::create(['facility_id'=>$f->id,'user_id'=>$user->id,'name'=>'管理者','role'=>'admin','is_active'=>true]);$child=\App\Models\Child::create(['facility_id'=>$f->id,'name'=>'確認児童','contract_status'=>'active']);return [$f,$user,$child]; }
 public function test_calendar_settings_are_read_from_the_active_facility_and_reject_stale_updates(): void {
  [$tenant,$actor]=$this->fixture();$this->actingAs($actor);
  $this->getJson('/absences/calendar')->assertOk()->assertJsonPath('calendar.confirmed',false)->assertJsonPath('calendar.revision',0);
  $calendar=['revision'=>0,'weekdays'=>[1,2,3,4,5],'closed_dates'=>['2026-05-06'],'confirmed'=>true];
  $this->putJson('/absences/calendar',$calendar)->assertOk();
  $this->getJson('/absences/calendar')->assertOk()->assertJsonPath('calendar.revision',1)->assertJsonPath('calendar.closed_dates.0','2026-05-06');
  $this->putJson('/absences/calendar',$calendar)->assertUnprocessable();
  [$other,$otherActor]=$this->fixture();$this->actingAs($otherActor);
  $this->getJson('/absences/calendar?facility_id='.$tenant->id)->assertOk()->assertJsonPath('calendar.confirmed',false)->assertJsonPath('calendar.revision',0);
  \App\Models\Staff::where('user_id',$actor->id)->update(['role'=>'staff']);$actor->unsetRelation('staff');$this->actingAs($actor);
  $this->getJson('/absences/calendar')->assertForbidden();
  $this->putJson('/absences/calendar',array_replace($calendar,['revision'=>1]))->assertForbidden();
 }
 public function test_drafts_conflicts_tenant_isolation_and_audit_history(): void {
  [$tenant,$actor,$child]=$this->fixture();$this->actingAs($actor);
  $payload=['date'=>'2026-05-07','child_id'=>$child->id,'revision'=>0,'payload'=>['is_absent'=>true,'reason'=>'発熱','contacted_at'=>'2026-05-07T09:00']];
  $this->putJson('/absences/data',$payload)->assertOk();
  $this->getJson('/absences/data?date=2026-05-07')->assertOk()->assertJsonPath('rows.0.payload.reason','発熱')->assertJsonPath('rows.0.assessment.status','記録不足');
  $this->putJson('/absences/data',$payload)->assertUnprocessable()->assertJsonValidationErrors('revision');
  $payload['revision']=1;$payload['payload']['is_absent']=false;$this->putJson('/absences/data',$payload)->assertOk();
  $this->assertDatabaseCount('absence_notices',1);$this->assertDatabaseCount('absence_notice_versions',2);
  $this->getJson('/absences/data?date=2026-05-07')->assertJsonPath('rows.0.assessment.status','欠席取消');
  [$other,$otherActor,$otherChild]=$this->fixture();$payload['child_id']=$otherChild->id;$payload['revision']=0;
  $this->putJson('/absences/data',$payload)->assertUnprocessable();
  $this->actingAs($otherActor)->getJson('/absences/data?date=2026-05-07')->assertJsonCount(0,'rows');
 }
 public function test_calendar_and_consultation_required_for_confirmation(): void {
  [$tenant,$actor,$child]=$this->fixture();$this->actingAs($actor);
  $payload=['date'=>'2026-05-07','child_id'=>$child->id,'revision'=>0,'payload'=>['is_absent'=>true,'claim_confirmed'=>true,'contacted_at'=>'2026-05-07T09:00']];
  $this->putJson('/absences/data',$payload)->assertUnprocessable()->assertJsonValidationErrors('claim_confirmed');$this->assertDatabaseCount('absence_notices',0);
  $calendar=['revision'=>0,'weekdays'=>[1,2,3,4,5],'closed_dates'=>['2026-05-04'],'confirmed'=>true];
  $this->putJson('/absences/calendar',$calendar)->assertOk();$this->putJson('/absences/calendar',$calendar)->assertUnprocessable();
 }
 public function test_linked_record_is_read_and_written_at_the_same_remote_endpoint(): void {
  [$tenant,$actor,$child]=$this->fixture(); $tenant->update(['yoyaku_business_id'=>99,'yoyaku_api_token'=>'fixture-token']);$child->update(['yoyaku_user_id'=>77]);
  config(['services.houkago_plus.base_url'=>'https://p.example.test']);
  \Illuminate\Support\Facades\Http::fake(['https://p.example.test/api/absences/data*'=>fn($request)=>$request->method()==='PUT' ? \Illuminate\Support\Facades\Http::response(['ok'=>true],200) : \Illuminate\Support\Facades\Http::response(['business_id'=>99,'date'=>'2026-05-07','children'=>[['id'=>77,'name'=>'連携児童']],'staff'=>[],'rows'=>[['child_id'=>77,'revision'=>1,'payload'=>['is_absent'=>true,'reason'=>'発熱'],'assessment'=>['status'=>'記録不足','eligible_candidate'=>false]]],'calendar'=>null,'can_confirm'=>true],200)]);
  $this->actingAs($actor)->getJson('/absences/data?date=2026-05-07')->assertOk()->assertJsonPath('rows.0.local_child_id',$child->id);
  $this->assertDatabaseCount('absence_notices',0);
  $this->putJson('/absences/data',['date'=>'2026-05-07','child_id'=>77,'revision'=>1,'payload'=>['is_absent'=>true,'reason'=>'回復待ち']])->assertOk();
  \App\Models\Staff::where('user_id',$actor->id)->update(['role'=>'staff']);$actor->unsetRelation('staff');
  $this->getJson('/absences/data?date=2026-05-07')->assertOk()->assertJsonPath('can_confirm',false);
  $this->putJson('/absences/data',['date'=>'2026-05-07','child_id'=>77,'revision'=>1,'payload'=>['is_absent'=>true,'claim_confirmed'=>true]])->assertForbidden();
  \Illuminate\Support\Facades\Http::assertSent(fn($req)=>$req->method()==='PUT' && $req['business_id']===99 && $req['child_id']===77 && $req->hasHeader('Authorization','Bearer fixture-token'));
 }
 public function test_absence_projection_removes_visit_without_writing_attendance_on_get(): void {
  [$tenant,$actor,$child]=$this->fixture();
  \App\Models\UsageRecord::create(['facility_id'=>$tenant->id,'child_id'=>$child->id,'date'=>'2026-05-07','status'=>'attended']);
  $this->actingAs($actor)->putJson('/absences/data',['date'=>'2026-05-07','child_id'=>$child->id,'revision'=>0,'payload'=>['is_absent'=>true,'reason'=>'発熱']])->assertOk();
  $this->get('/dashboard?date=2026-05-07')->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->has('visits',0)->has('absences',1)->where('absences.0.reason','発熱')->where('attendanceSummary.attended',0));
  $this->assertDatabaseHas('usage_records',['child_id'=>$child->id,'status'=>'attended']);
 }

 public function test_normal_confirmation_checks_full_records_month_limit_and_reused_consultation(): void {
  [$tenant,$actor,$child]=$this->fixture();$this->actingAs($actor);
  $this->putJson('/absences/calendar',['revision'=>0,'weekdays'=>[1,2,3,4,5,6,7],'closed_dates'=>[],'confirmed'=>true])->assertOk();
  $data=$this->getJson('/absences/data?date=2026-05-07')->json();$staff=$data['staff'][0]['id'];
  foreach(['2026-05-07','2026-05-08','2026-05-09','2026-05-10'] as $date) {
   $payload=['is_absent'=>true,'claim_confirmed'=>true,'contacted_at'=>$date.'T09:00','reason'=>'発熱','contact_method'=>'tel','caller'=>'母','receiver_staff_id'=>$staff,'consultant_staff_id'=>$staff,'consultant_role'=>'児童指導員','child_condition'=>'体温と食事の状態を確認','support_content'=>'療養中の過ごし方と次回利用を相談','next_date'=>\Carbon\Carbon::parse($date)->addDay()->toDateString(),'was_planned'=>true,'sudden'=>true,'service_available'=>true,'consultation_done'=>true];
   $this->putJson('/absences/data',['date'=>$date,'child_id'=>$child->id,'revision'=>0,'payload'=>$payload])->assertOk();
  }
  $payload['contacted_at']='2026-05-11T09:00';$payload['next_date']='2026-05-12';
  $this->putJson('/absences/data',['date'=>'2026-05-11','child_id'=>$child->id,'revision'=>0,'payload'=>$payload])->assertUnprocessable()->assertJsonValidationErrors('claim_confirmed');
  $payload['claim_confirmed']=false;
  $this->putJson('/absences/data',['date'=>'2026-05-11','child_id'=>$child->id,'revision'=>0,'payload'=>$payload])->assertOk();
  $this->getJson('/absences/data?date=2026-05-11')->assertJsonPath('rows.0.assessment.status','要確認')->assertJsonPath('rows.0.assessment.other_confirmed',4);
 }

}
