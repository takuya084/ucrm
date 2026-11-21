<?php
namespace App\Http\Controllers;
use App\Services\{AbsenceBoard,AbsenceRegister,YoyakuApiService};
use App\Models\Facility;
use App\Http\Requests\SaveAbsenceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
class AbsenceController extends Controller {
 private function canConfirm(): bool { return in_array(auth()->user()->staff?->role,['admin','leader'],true); }
 public function index(Request $r) { $date=$r->validate(['date'=>['nullable','date_format:Y-m-d']])['date'] ?? today()->toDateString(); return Inertia::render('Absences/Index',['date'=>$date]); }
 public function show(Request $r,AbsenceBoard $board) { $date=$r->validate(['date'=>['required','date_format:Y-m-d']])['date']; return response()->json(array_replace($board->read($this->facilityId(),$date),['can_confirm'=>$this->canConfirm()]))->header('Cache-Control','no-store'); }
 public function update(SaveAbsenceRequest $r,AbsenceRegister $register,YoyakuApiService $api) {
  $input=$r->validated(); abort_if(($input['payload']['claim_confirmed'] ?? false) && !$this->canConfirm(),403);
  $facility=Facility::findOrFail($this->facilityId());
  if($facility->yoyaku_business_id) $api->absences('PUT',$input+['business_id'=>(int)$facility->yoyaku_business_id],$facility->id);
  else $register->save($facility->id,$r->user()->id,$input);
  return response()->json(['ok'=>true]);
 }
 public function calendar(Request $r,AbsenceRegister $register,YoyakuApiService $api) {
  abort_unless($this->canConfirm(),403);
  $input=$r->validate(['revision'=>['required','integer','min:0'],'weekdays'=>['required','array','min:1','max:7'],'weekdays.*'=>['integer','between:1,7','distinct'],'closed_dates'=>['present','array','max:1000'],'closed_dates.*'=>['date_format:Y-m-d','distinct'],'confirmed'=>['required','accepted']]); $input['confirmed']=true;
  $facility=Facility::findOrFail($this->facilityId());
  if($facility->yoyaku_business_id) $api->absences('PUT',$input+['business_id'=>(int)$facility->yoyaku_business_id],$facility->id,true); else $register->saveCalendar($facility->id,$input);
  return response()->json(['ok'=>true]);
 }
}
