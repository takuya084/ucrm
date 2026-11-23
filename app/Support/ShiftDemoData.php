<?php

namespace App\Support;

use App\Models\{Facility, User, Staff, ShiftLabel, StaffWorkPattern, StaffQualification, MonthlyShift, ShiftEntry, ShiftDayNote, Child, ChildSchedule, School};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Hash};
use RuntimeException;

final class ShiftDemoData
{
    public const EMAIL = 'shift-demo-admin@example.invalid';
    public const FACILITY = '【デモ】シフト確認用（職員10名）';

    public function create(string $month, string $password): array
    {
        if (!app()->environment('testing') && !(PHP_SAPI === 'cli' && getenv('MANUAL_DEMO') === '1'
            && getenv('DEMO_APP') === 'u' && @file_get_contents('/opt/manual-demo-marker') === 'local-manual-demo-v1'
            && !is_file(base_path('.env')) && !is_file(base_path('bootstrap/cache/config.php')))) {
            throw new RuntimeException('manual_demo_only');
        }
        if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $month) || strlen($password) < 20) throw new RuntimeException('invalid_demo_parameters');
        if (User::where('email', self::EMAIL)->exists() || Facility::where('name', self::FACILITY)->exists()) throw new RuntimeException('demo_already_exists_no_overwrite');
        return DB::transaction(function () use ($month, $password) {
            $facility = Facility::create(['name'=>self::FACILITY, 'capacity_per_day'=>10, 'billing_type'=>'free', 'is_active'=>true]);
            $labels = [
                ['早番',false,8], ['遅番',false,8], ['午前',false,4], ['午後',false,4], ['送迎',false,3],
                ['休み',true,0], ['有給',true,0],
            ];
            foreach ($labels as $i => [$name,$off,$hours]) ShiftLabel::create(['facility_id'=>$facility->id,'name'=>$name,'is_off'=>$off,'work_hours'=>$hours,'display_order'=>$i]);
            $people = [
                ['管理者','admin',['jidou_shidouin'],'full_time'],
                ['児発管','leader',['hoikushi'],'full_time'],
                ['早番担当','staff',['hoikushi'],'full_time'],
                ['遅番担当','staff',['jidou_shidouin'],'full_time'],
                ['午前パート','staff',['hoikushi'],'part_time'],
                ['午後パート','staff',['jidou_shidouin'],'part_time'],
                ['理学療法士','staff',['pt'],'part_time'],
                ['研修修了職員','staff',['jidou_shidouin','kyoudo'],'full_time'],
                ['送迎担当A','driver',[],'part_time'],
                ['送迎担当B','driver',[],'part_time'],
            ];
            $staff = [];
            foreach ($people as $i => [$name,$role,$quals,$employment]) {
                $user = User::create(['name'=>'【デモ】'.sprintf('%02d',$i+1).' '.$name,
                    'email'=>$i===0 ? self::EMAIL : 'shift-demo-'.($i+1).'@example.invalid',
                    'password'=>Hash::make($i===0 ? $password : bin2hex(random_bytes(24)))]);
                $user->forceFill(['email_verified_at'=>now()])->save();
                $s = Staff::create(['facility_id'=>$facility->id,'user_id'=>$user->id,'name'=>$user->name,'role'=>$role,
                    'is_active'=>true,'employment_type'=>$employment,'display_order'=>$i+1,
                    'monthly_salary'=>$employment==='full_time'?280000:null,'hourly_wage'=>$employment==='part_time'?1400:null]);
                foreach ($quals as $qualification) StaffQualification::create(['staff_id'=>$s->id,'qualification'=>$qualification]);
                $staff[] = $s;
            }
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            $baseTypes = ['早番','早番','早番','遅番','午前','午後','午後','遅番','送迎','送迎'];
            $baseTimes = ['09:00','09:00','08:30','10:00','09:30','13:00','14:15','10:30','13:30','14:00'];
            foreach ($staff as $i => $s) foreach ($days as $di => $day) {
                $off = $di===6 || ($di===5 && $i%3===0) || ($i>=4 && $di===$i%5);
                StaffWorkPattern::create(['facility_id'=>$facility->id,'staff_id'=>$s->id,'day_of_week'=>$day,
                    'work_type'=>$off?'休み':$baseTypes[$i], 'start_time'=>$off?null:$baseTimes[$i]]);
            }
            $school = School::create(['facility_id'=>$facility->id,'name'=>'【デモ】サンプル小学校','school_type'=>'elementary']);
            for ($i=0; $i<12; $i++) {
                $child = Child::create(['facility_id'=>$facility->id,'school_id'=>$school->id,
                    'name'=>'【デモ】児童'.sprintf('%02d',$i+1),'name_kana'=>'デモジドウ','birthdate'=>'2018-04-02',
                    'contract_status'=>'active','contract_start_date'=>'2026-01-01']);
                foreach (array_slice($days,0,6) as $di=>$day) if (($i+$di)%4!==0 && ($di!==5 || $i<5)) {
                    ChildSchedule::create(['child_id'=>$child->id,'day_of_week'=>$day,'start_date'=>'2026-01-01','status'=>'regular']);
                }
            }
            $shifts = [];
            for ($mi=0; $mi<2; $mi++) {
                $first = Carbon::parse($month.'-01')->addMonthsNoOverflow($mi);
                $shift = MonthlyShift::create(['facility_id'=>$facility->id,'year'=>$first->year,'month'=>$first->month,'status'=>'draft','created_by'=>$staff[0]->id]);
                $special = []; $weekday = 0;
                for ($date=$first->copy(); $date->month===$first->month; $date->addDay()) {
                    $day = $days[$date->dayOfWeekIso-1];
                    if (!$date->isWeekend()) $weekday++;
                    // Distinct examples: paid leave, many absences, no leader, no childcare qualifications.
                    $case = !$date->isWeekend() ? ([2=>'paid',4=>'shortage',6=>'no-leader',8=>'no-qualified'][$weekday]??'') : '';
                    foreach ($staff as $i=>$s) {
                        $p = $s->workPatterns()->where('day_of_week',$day)->firstOrFail();
                        $type=$p->work_type; $time=$p->start_time;
                        if ($case==='paid' && $i===2) {$type='有給';$time=null;}
                        if ($case==='shortage' && $i!==0) {$type='休み';$time=null;}
                        if ($case==='no-leader' && $i===1) {$type='休み';$time=null;}
                        if ($case==='no-qualified' && !in_array($i,[6,8,9],true)) {$type='休み';$time=null;}
                        ShiftEntry::create(['monthly_shift_id'=>$shift->id,'staff_id'=>$s->id,'date'=>$date->toDateString(),'work_type'=>$type,'start_time'=>$time]);
                    }
                    $note = ['paid'=>'【デモ】有給休暇の例','shortage'=>'【デモ】職員不足の警告例','no-leader'=>'【デモ】児発管不在の警告例','no-qualified'=>'【デモ】有資格者不足の警告例'][$case]??null;
                    if (!$note && $date->isSunday()) $note='【デモ】休業日';
                    if (!$note && $date->day===1) $note='【デモ】月初ミーティング';
                    if ($note) ShiftDayNote::create(['monthly_shift_id'=>$shift->id,'date'=>$date->toDateString(),'note'=>$note]);
                    if ($case) $special[$case]=$date->toDateString();
                }
                $shifts[]=['id'=>$shift->id,'month'=>$first->format('Y-m'),'examples'=>$special];
            }
            return ['facility_id'=>$facility->id,'facility_name'=>self::FACILITY,'email'=>self::EMAIL,'shifts'=>$shifts,'staff_count'=>10];
        });
    }
}
