<?php
namespace App\Services;
use App\Models\{Facility,Child};
class AbsenceBoard {
 private array $cache=[];
 public function overview(int $id,string $date): array {
  $key=$id.':'.$date; if(isset($this->cache[$key])) return $this->cache[$key];
  try { return $this->cache[$key]=$this->read($id,$date)+['available'=>true]; }
  catch (\Throwable $e) { report($e); return $this->cache[$key]=['available'=>false,'rows'=>[],'children'=>[]]; }
 }
 public function read(int $facilityId,string $date): array {
  $facility=Facility::findOrFail($facilityId);
  if($facility->yoyaku_business_id) {
   $data=app(YoyakuApiService::class)->absences('GET',['business_id'=>(int)$facility->yoyaku_business_id,'date'=>$date],$facilityId);
   // Dashboard IDs must belong to this UCRM facility, never remote IDs or another facility.
   $map=Child::where('facility_id',$facilityId)->whereNotNull('yoyaku_user_id')->pluck('id','yoyaku_user_id');
   foreach($data['rows'] as &$row) $row['local_child_id']=$map[$row['child_id']] ?? null;
   return $data + ['linked'=>true];
  }
  $r=app(AbsenceRegister::class);
  $rows=$r->rows($facilityId,$date); foreach($rows as &$row) $row['local_child_id']=$row['child_id'];
  return ['date'=>$date,'children'=>$r->children($facilityId),'staff'=>$r->staff($facilityId),'rows'=>$rows,'calendar'=>$r->calendar($facilityId),'linked'=>false];
 }
}
