<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class SaveAbsenceRequest extends FormRequest {
 public function authorize(): bool { return $this->user() !== null; }
 public function rules(): array { return [
  'business_id'=>['sometimes','integer','min:1'],'date'=>['required','date_format:Y-m-d'],
  'child_id'=>['required','integer','min:1'],'revision'=>['required','integer','min:0'],
  'payload'=>['required','array:is_absent,contacted_at,reason,contact_method,caller,receiver_staff_id,consultant_staff_id,consultant_role,child_condition,support_content,next_date,was_planned,sudden,service_available,consultation_done,special_case,claim_confirmed'],
  'payload.is_absent'=>['required','boolean'], 'payload.contacted_at'=>['nullable','date_format:Y-m-d\\TH:i','before_or_equal:now'],
  'payload.reason'=>['nullable','string','max:2000'], 'payload.contact_method'=>['nullable','in:tel,line,app,in_person,email,other'],
  'payload.caller'=>['nullable','string','max:120'],'payload.receiver_staff_id'=>['nullable','integer','min:1'],
  'payload.consultant_staff_id'=>['nullable','integer','min:1'],'payload.consultant_role'=>['nullable','string','max:120'],
  'payload.child_condition'=>['nullable','string','max:5000'],'payload.support_content'=>['nullable','string','max:5000'],
  'payload.next_date'=>['nullable','date_format:Y-m-d','after:date'],
  'payload.was_planned'=>['nullable','boolean'],'payload.sudden'=>['nullable','boolean'],'payload.service_available'=>['nullable','boolean'],
  'payload.consultation_done'=>['nullable','boolean'],'payload.special_case'=>['sometimes','boolean'],'payload.claim_confirmed'=>['sometimes','boolean'],
 ]; }
}
