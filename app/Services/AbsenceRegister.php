<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbsenceRegister
{
    public function rows(int $tenant, string $date): array
    {
        $month = substr($date, 0, 7);
        $records = DB::table('absence_notices')->where('tenant_id', $tenant)->whereBetween('date', [$month.'-01', date('Y-m-t', strtotime($date))])->orderBy('date')->get();
        $calendar = $this->calendar($tenant);
        return $records->where('date', $date)->map(function ($row) use ($records,$calendar) {
            $payload = json_decode($row->payload,true);
            $others = $records->filter(fn ($other) => $other->id !== $row->id && $other->child_id === $row->child_id)->map(fn ($other) => json_decode($other->payload,true));
            $count = $others->filter(fn ($p) => ($p['is_absent'] ?? true) && ($p['claim_confirmed'] ?? false))->count();
            $reused = DB::table('absence_notices')->where('tenant_id',$row->tenant_id)->where('child_id',$row->child_id)->where('id','!=',$row->id)->get()->map(fn($r)=>json_decode($r->payload,true))->contains(fn ($p) => ($p['is_absent'] ?? true) && !empty($payload['contacted_at']) && ($p['contacted_at'] ?? null) === $payload['contacted_at']);
            return ['child_id'=>(int)$row->child_id,'date'=>$row->date,'revision'=>(int)$row->revision,'payload'=>$payload,'assessment'=>app(AbsenceRules::class)->assess($payload,$row->date,$calendar,$count,$reused)];
        })->values()->all();
    }

    public function calendar(int $tenant): ?array
    {
        $row = DB::table('absence_calendars')->where('tenant_id',$tenant)->first();
        return $row ? json_decode($row->payload,true) + ['revision'=>(int)$row->revision] : $this->defaultCalendar($tenant);
    }

    public function saveCalendar(int $tenant, array $input): void
    {
        DB::transaction(function () use ($tenant,$input) {
            $this->lockTenant($tenant);
            $row = DB::table('absence_calendars')->where('tenant_id',$tenant)->first();
            if ((int)($row->revision ?? 0) !== $input['revision']) throw ValidationException::withMessages(['calendar'=>'営業日設定が変更されています。一覧を再読み込みしてください。']);
            $payload = array_intersect_key($input,array_flip(['weekdays','closed_dates','confirmed']));
            DB::table('absence_calendars')->updateOrInsert(['tenant_id'=>$tenant],['payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'revision'=>$input['revision']+1,'created_at'=>$row->created_at ?? now(),'updated_at'=>now()]);
        });
    }

    public function save(int $tenant, int $actor, array $input): void
    {
        DB::transaction(function () use ($tenant,$actor,$input) {
            $this->lockTenant($tenant);
            $this->assertChild($tenant,$input['child_id']);
            $row = DB::table('absence_notices')->where('tenant_id',$tenant)->where('child_id',$input['child_id'])->where('date',$input['date'])->first();
            if ((int)($row->revision ?? 0) !== $input['revision']) throw ValidationException::withMessages(['revision'=>'別の職員が変更しました。入力内容を控え、最新の一覧を確認してください。']);
            $payload = $input['payload'];
            foreach (['receiver_staff_id'=>'receiver_name','consultant_staff_id'=>'consultant_name'] as $field=>$name) {
                $person = empty($payload[$field]) ? null : $this->staff($tenant)->firstWhere('id',(int)$payload[$field]);
                if (!empty($payload[$field]) && !$person) throw ValidationException::withMessages([$field=>'同じ事業所の職員を選択してください。']);
                $payload[$name] = $person['name'] ?? null;
            }
            // A confirmation is a review marker, not a billing submission.
            if ($payload['claim_confirmed'] ?? false) {
                $old = $row ? json_decode($row->payload,true) : [];
                $all = DB::table('absence_notices')->where('tenant_id',$tenant)->where('child_id',$input['child_id'])->whereBetween('date',[substr($input['date'],0,7).'-01',date('Y-m-t',strtotime($input['date']))])->where('date','!=',$input['date'])->get()->map(fn($r)=>json_decode($r->payload,true));
                $count = $all->filter(fn($p)=>($p['is_absent']??true)&&($p['claim_confirmed']??false))->count();
                $reused = DB::table('absence_notices')->where('tenant_id',$tenant)->where('child_id',$input['child_id'])->where('date','!=',$input['date'])->get()->map(fn($r)=>json_decode($r->payload,true))->contains(fn($p)=>($p['is_absent']??true)&&!empty($payload['contacted_at'])&&($p['contacted_at']??null)===$payload['contacted_at']);
                $result = app(AbsenceRules::class)->assess($payload,$input['date'],$this->calendar($tenant),$count,$reused);
                if (!$result['eligible_candidate']) throw ValidationException::withMessages(['claim_confirmed'=>'日付・記録・月回数の条件を確認してください。対象外・記録不足・特例は通常の算定確認を付けられません。']);
            }
            $values = ['revision'=>$input['revision']+1,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'updated_at'=>now()];
            if ($row) { DB::table('absence_notices')->where('id',$row->id)->update($values); $id=$row->id; }
            else $id=DB::table('absence_notices')->insertGetId($values+['tenant_id'=>$tenant,'child_id'=>$input['child_id'],'date'=>$input['date'],'created_at'=>now()]);
            DB::table('absence_notice_versions')->insert(['absence_notice_id'=>$id,'actor_id'=>$actor,'revision'=>$values['revision'],'payload'=>$values['payload'],'created_at'=>now()]);
        });
    }
    protected function lockTenant(int $id): void { \App\Models\Facility::whereKey($id)->lockForUpdate()->firstOrFail(); }
    protected function assertChild(int $tenant,int $id): void { abort_unless(\App\Models\Child::where('facility_id',$tenant)->whereKey($id)->exists(),422,'同じ事業所の児童を選択してください。'); }
    public function children(int $tenant): array { return \App\Models\Child::where('facility_id',$tenant)->where('contract_status','active')->orderBy('name_kana')->get(['id','name'])->toArray(); }
    public function staff(int $tenant): \Illuminate\Support\Collection { return \App\Models\Staff::where('facility_id',$tenant)->where('is_active',true)->orderBy('name')->get(['id','name'])->map(fn($p)=>['id'=>$p->id,'name'=>$p->name]); }
    protected function defaultCalendar(int $tenant): ?array { return ['weekdays'=>[],'closed_dates'=>[],'confirmed'=>false,'revision'=>0]; }

}
