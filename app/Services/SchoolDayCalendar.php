<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SchoolDayCalendar
{
    public static function value(int $facilityId,int $childId,string $date): ?bool
    {
        $value=DB::table('child_school_days')->where('facility_id',$facilityId)->where('child_id',$childId)->where('date',$date)->value('is_school_day');
        return $value===null?null:(bool)$value;
    }

    public static function metadata(int $facilityId,int $childId,string $date): array
    {
        $type=ChildServiceType::forDate($facilityId,$childId,$date);$value=self::value($facilityId,$childId,$date);
        return ['service_type'=>$type['type'],'service_label'=>$type['label'],'service_reason'=>$type['reason'],
            'school_day_confirmed'=>$value!==null,'school_day_label'=>$type['type']==='jidou'?'対象外':($value===null?'未設定':($value?'学校日':'休業日'))];
    }
}
