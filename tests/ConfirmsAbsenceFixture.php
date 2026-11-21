<?php
namespace Tests;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\DB;
trait ConfirmsAbsenceFixture {
 private function confirmAbsence(UsageRecord $record): void {
  $tenant=$record->facility_id;
  DB::table('absence_calendars')->updateOrInsert(['tenant_id'=>$tenant],['payload'=>json_encode(['confirmed'=>true,'weekdays'=>[1,2,3,4,5,6,7],'closed_dates'=>[]]),'revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $count=DB::table('absence_notices')->where('tenant_id',$tenant)->where('child_id',$record->child_id)->count();
  if($count>=4)return;
  $payload=['is_absent'=>true,'claim_confirmed'=>true,'contacted_at'=>$record->date->format('Y-m-d').'T09:00','reason'=>'発熱','contact_method'=>'tel','caller'=>'母','receiver_staff_id'=>1,'consultant_staff_id'=>1,'consultant_role'=>'児童指導員','child_condition'=>'体調を確認','support_content'=>'回復後の利用予定を調整','next_date'=>$record->date->copy()->addDay()->format('Y-m-d'),'was_planned'=>true,'sudden'=>true,'service_available'=>true,'consultation_done'=>true];
  DB::table('absence_notices')->insert(['tenant_id'=>$tenant,'child_id'=>$record->child_id,'date'=>$record->date->format('Y-m-d'),'revision'=>1,'payload'=>json_encode($payload),'created_at'=>now(),'updated_at'=>now()]);
 }
}
