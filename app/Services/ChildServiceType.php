<?php

namespace App\Services;

use App\Models\RecipientCertificate;
use Illuminate\Support\Collection;

class ChildServiceType
{
    public const LABELS=['houday'=>'放課後等デイサービス','jidou'=>'児童発達支援'];

    public static function fromCertificates(Collection $certificates,string $date): array
    {
        $valid=$certificates->filter(fn($certificate)=>$certificate->status==='active'
            && (!$certificate->valid_from || $certificate->valid_from->format('Y-m-d')<=$date)
            && (!$certificate->valid_to || $certificate->valid_to->format('Y-m-d')>=$date));
        $certificate=$valid->count()===1?$valid->first():null;
        $type=$certificate && isset(self::LABELS[$certificate->service_type])?$certificate->service_type:null;
        return ['type'=>$type,'label'=>self::LABELS[$type]??'未設定',
            'certificate_id'=>$certificate?->id,
            'reason'=>$type?null:($valid->count()>1?'有効な受給者証が重複しています。':($certificate?'受給者証のサービス種別が未設定です。':'この日に有効な受給者証がありません。'))];
    }

    public static function forDate(int $facilityId,int $childId,string $date): array
    {
        return self::fromCertificates(RecipientCertificate::where('child_id',$childId)
            ->whereHas('child',fn($q)=>$q->where('facility_id',$facilityId))->get(),$date);
    }
}
