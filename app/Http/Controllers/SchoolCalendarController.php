<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog,BillingPeriod,Child,Facility,UsageRecord};
use App\Services\ChildServiceType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SchoolCalendarController extends Controller
{
    public function index(Request $request)
    {
        $input=$request->validate(['month'=>['nullable','date_format:Y-m']]);
        $month=$input['month']??now()->format('Y-m');$facilityId=$this->facilityId();
        $first=$month.'-01';$last=Carbon::parse($first)->endOfMonth()->format('Y-m-d');
        $days=collect(range(1,Carbon::parse($first)->daysInMonth))->map(fn($n)=>[
            'date'=>sprintf('%s-%02d',$month,$n),'day'=>$n,'weekday'=>Carbon::parse(sprintf('%s-%02d',$month,$n))->dayOfWeek,
        ]);
        $entries=DB::table('child_school_days')->where('facility_id',$facilityId)->whereBetween('date',[$first,$last])->get()->groupBy('child_id');
        $children=Child::where('facility_id',$facilityId)->where(fn($q)=>$q->where('contract_status','active')
            ->orWhereIn('id',UsageRecord::where('facility_id',$facilityId)->whereDate('date','>=',$first)->whereDate('date','<=',$last)->select('child_id'))
            ->orWhereIn('id',$entries->keys()))->with(['school:id,name','recipientCertificates'])->orderBy('name_kana')->orderBy('id')->get();
        $rows=$children->map(function($child)use($days,$entries){
            $saved=($entries->get($child->id)??collect())->keyBy('date');
            return ['id'=>$child->id,'name'=>$child->name,'school'=>$child->school?->name,
                'services'=>$days->mapWithKeys(fn($day)=>[$day['date']=>ChildServiceType::fromCertificates($child->recipientCertificates,$day['date'])]),
                'values'=>$days->mapWithKeys(fn($day)=>[$day['date']=>$saved->has($day['date'])?(bool)$saved[$day['date']]->is_school_day:null])];
        });
        return Inertia::render('SchoolCalendar/Index',['month'=>$month,'days'=>$days,'rows'=>$rows,
            'revision'=>(int)(DB::table('school_calendar_months')->where('facility_id',$facilityId)->where('month',$month)->value('revision')??0),
            'locked'=>BillingPeriod::where('facility_id',$facilityId)->where('year_month',$month)->first()?->isLocked()??false]);
    }

    public function update(Request $request)
    {
        $facilityId=$this->facilityId();
        $input=$request->validate(['month'=>['required','date_format:Y-m'],'revision'=>['required','integer','min:0'],
            'cells'=>['required','array','min:1','max:20000'],'cells.*.child_id'=>['required','integer',Rule::exists('children','id')->where('facility_id',$facilityId)->whereNull('deleted_at')],
            'cells.*.date'=>['required','date_format:Y-m-d'],'cells.*.value'=>['present','nullable','boolean']]);
        $seen=[];
        foreach($input['cells'] as $cell){
            $key=$cell['child_id'].':'.$cell['date'];
            if(substr($cell['date'],0,7)!==$input['month'] || isset($seen[$key])) throw ValidationException::withMessages(['cells'=>'対象月の日付を重複なく指定してください。']);
            $seen[$key]=true;
        }
        return DB::transaction(function()use($input,$facilityId){
            $facility=Facility::whereKey($facilityId)->lockForUpdate()->firstOrFail();
            $period=BillingPeriod::where('facility_id',$facilityId)->where('year_month',$input['month'])->lockForUpdate()->first();
            abort_if($period?->isLocked(),423,'請求確定済みの月は変更できません。');
            DB::table('school_calendar_months')->insertOrIgnore(['facility_id'=>$facilityId,'month'=>$input['month'],'revision'=>0,'created_at'=>now(),'updated_at'=>now()]);
            $state=DB::table('school_calendar_months')->where('facility_id',$facilityId)->where('month',$input['month'])->lockForUpdate()->first();
            abort_if((int)$state->revision!==(int)$input['revision'],409,'他の職員が更新しました。入力内容を控え、最新の状態を確認してください。');
            foreach($input['cells'] as $cell){
                $type=ChildServiceType::forDate($facilityId,(int)$cell['child_id'],$cell['date']);
                if($cell['value']!==null && $type['type']!=='houday') throw ValidationException::withMessages(['cells'=>'学校日・休業日の設定には、その日の受給者証で放課後等デイサービスの登録が必要です。']);
                $key=['facility_id'=>$facilityId,'child_id'=>$cell['child_id'],'date'=>$cell['date']];
                if($cell['value']===null) DB::table('child_school_days')->where($key)->delete();
                else {
                    $existing=DB::table('child_school_days')->where($key)->first();
                    DB::table('child_school_days')->updateOrInsert($key,['is_school_day'=>(bool)$cell['value'],'updated_by'=>auth()->user()->staff->id,
                        'created_at'=>$existing?->created_at??now(),'updated_at'=>now()]);
                    UsageRecord::where('facility_id',$facilityId)->where('child_id',$cell['child_id'])->whereDate('date',$cell['date'])
                        ->update(['is_school_day'=>(bool)$cell['value']]);
                }
            }
            DB::table('school_calendar_months')->where('id',$state->id)->update(['revision'=>$state->revision+1,'updated_at'=>now()]);
            AuditLog::record('updated',$facility,null,['school_calendar_month'=>$input['month'],'changes'=>$input['cells']]);
            return response()->json(['revision'=>$state->revision+1,'message'=>'学校日・休業日を保存しました。']);
        });
    }
}
