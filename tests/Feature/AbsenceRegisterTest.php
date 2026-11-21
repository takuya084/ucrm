<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AbsenceRegisterTest extends TestCase {
 use RefreshDatabase;
 private function fixture(): array { $f=\App\Models\Facility::create(['name'=>'確認事業所','facility_code'=>(string)random_int(1000000000,1999999999),'service_type'=>'houday']);$user=\App\Models\User::factory()->create();\App\Models\Staff::create(['facility_id'=>$f->id,'user_id'=>$user->id,'name'=>'管理者','role'=>'admin','is_active'=>true]);$child=\App\Models\Child::create(['facility_id'=>$f->id,'name'=>'確認児童','contract_status'=>'active']);return [$f,$user,$child]; }
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
  \Illuminate\Support\Facades\Http::fake(['https://p.example.test/api/absences/data*'=>\Illuminate\Support\Facades\Http::response(['business_id'=>99,'date'=>'2026-05-07','children'=>[['id'=>77,'name'=>'連携児童']],'staff'=>[],'rows'=>[['child_id'=>77,'revision'=>1,'payload'=>['is_absent'=>true,'reason'=>'発熱'],'assessment'=>['status'=>'記録不足','eligible_candidate'=>false]]],'calendar'=>null],200)]);
  $this->actingAs($actor)->getJson('/absences/data?date=2026-05-07')->assertOk()->assertJsonPath('rows.0.local_child_id',$child->id);
  $this->assertDatabaseCount('absence_notices',0);
  \Illuminate\Support\Facades\Http::fake(['https://p.example.test/api/absences/data'=>\Illuminate\Support\Facades\Http::response(['ok'=>true],200)]);
  $this->putJson('/absences/data',['date'=>'2026-05-07','child_id'=>77,'revision'=>1,'payload'=>['is_absent'=>true,'reason'=>'回復待ち']])->assertOk();
  \Illuminate\Support\Facades\Http::assertSent(fn($req)=>$req->method()==='PUT' && $req['business_id']===99 && $req['child_id']===77 && $req->hasHeader('Authorization','Bearer fixture-token'));
 }
 public function test_absence_projection_removes_visit_without_writing_attendance_on_get(): void {
  [$tenant,$actor,$child]=$this->fixture();
  \App\Models\UsageRecord::create(['facility_id'=>$tenant->id,'child_id'=>$child->id,'date'=>'2026-05-07','status'=>'attended']);
  $this->actingAs($actor)->putJson('/absences/data',['date'=>'2026-05-07','child_id'=>$child->id,'revision'=>0,'payload'=>['is_absent'=>true,'reason'=>'発熱']])->assertOk();
  $this->get('/dashboard?date=2026-05-07')->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->has('visits',0)->has('absences',1)->where('absences.0.reason','発熱')->where('attendanceSummary.attended',0));
  $this->assertDatabaseHas('usage_records',['child_id'=>$child->id,'status'=>'attended']);
 }

}
