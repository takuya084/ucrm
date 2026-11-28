<?php
use Illuminate\Support\Facades\{Artisan, DB, Hash, Schema};

function reviewSamplesContext(string $kind): array
{
    if ($kind === 'p') {
        $business=\App\Models\Business::whereKey(77)->sole();
        $admin=\App\Models\User::where('business_id',77)->where('access_role','admin')->sole();
        return ['business'=>(int)$business->id,'admin_user'=>(int)$admin->id];
    }
    $facility=\App\Models\Facility::where('yoyaku_business_id',77)->sole();
    $staff=\App\Models\Staff::where('facility_id',$facility->id)->where('role','admin')->sole();
    return ['facility'=>(int)$facility->id,'admin_staff'=>(int)$staff->id,'admin_user'=>(int)$staff->user_id];
}

function reviewSamplesEmpty(string $kind,array $spec): void
{
    $expected=['users'=>1,'staff'=>1];
    foreach(array_unique(array_column($spec['operations'][$kind],'table')) as $table) {
        // Real audit rows from login/viewing are legitimate; never clear them.
        if($table==='audit_logs')continue;
        if(DB::table($table)->count()!==($expected[$table]??0))throw new RuntimeException('Existing records found; nothing replaced: '.$table);
    }
    reviewSamplesContext($kind);
}

function reviewSamplesVerify(string $kind,array $spec,bool $exact=true): array
{
    $counts=[];
    foreach($spec['counts'][$kind] as $table=>$added) {
        $count=DB::table($table)->count();$minimum=$added+(['users'=>1,'staff'=>1][$table]??0);
        if(($exact && $table!=='audit_logs' && $count!==$minimum)||$count<$minimum)throw new RuntimeException('Sample row count mismatch: '.$table);
        $counts[$table]=$count;
    }
    if(DB::connection()->getPdo()->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('Sample foreign key check failed');
    if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('Sample encryption check failed');
    $raw=DB::connection()->getPdo()->query('SELECT name FROM users WHERE id=1001')->fetchColumn();
    if($kind==='u')$raw=DB::connection()->getPdo()->query('SELECT name FROM children WHERE id=1001')->fetchColumn();
    if(!is_string($raw)||!str_starts_with($raw,\App\Privacy\Cipher::PREFIX))throw new RuntimeException('Sample name stored without encryption');
    if($kind==='p') {
        if(\App\Models\User::findOrFail(1001)->name!=='【架空】青空 はると')throw new RuntimeException('Sample decryption failed');
        if(!\Illuminate\Support\Facades\Auth::validate(['email'=>'child-01@example.test','password'=>'Demo-local-2026!']))throw new RuntimeException('Sample guardian login failed');
        // Cancellation fixtures must not leave a reservation behind.
        foreach($spec['cancelled']??[] as $row)if(\App\Models\Yoyaku::where('user_id',$row['user_id'])->where('date',$row['date'])->exists())throw new RuntimeException('Cancelled sample still reserved');
    }else{
        if(\App\Models\Child::findOrFail(1001)->name!=='【架空】青空 はると')throw new RuntimeException('Sample decryption failed');
        if(!\Illuminate\Support\Facades\Auth::validate(['email'=>'staff-01@example.test','password'=>'Demo-local-2026!']))throw new RuntimeException('Sample staff login failed');
        // Model casts/relations used by the review screens must remain readable.
        foreach(\App\Models\Child::with(['school','guardians','recipientCertificates'])->get() as $child)$child->toArray();
        foreach(\App\Models\ContactNote::with(['child','supportRecord.programs'])->get() as $note)$note->toArray();
        foreach(\App\Models\BillingDetail::with(['child','billingDetailLines'])->get() as $detail)$detail->toArray();
        foreach(\App\Models\MonthlyShift::with('entries')->get() as $shift)$shift->toArray();
        if(DB::table('usage_records')->where('date','>',$spec['anchor'])->exists())throw new RuntimeException('Future attendance fabricated');
        if(DB::table('usage_records')->whereNull('is_school_day')->exists())throw new RuntimeException('Attendance school-day flag must be present');
        foreach(\App\Models\DailyActivityPlan::all() as $activity)if(!array_key_exists($activity->category,\App\Models\DailyActivityPlan::CATEGORIES))throw new RuntimeException('Unregistered activity category');
    }
    return ['version'=>$spec['version'],'anchor'=>$spec['anchor'],'counts'=>$counts,'encrypted'=>true,'foreign_keys'=>true];
}

function reviewSamplesPopulate(string $kind,array $spec): array
{
    reviewSamplesEmpty($kind,$spec);
    $ids=reviewSamplesContext($kind);$password=Hash::make('Demo-local-2026!');$columns=[];
    $resolve=function($value)use(&$resolve,&$ids,$password){
        if(!is_array($value))return $value;
        if(array_keys($value)===['$ref']) {
            if(!array_key_exists($value['$ref'],$ids))throw new RuntimeException('Unknown sample reference: '.$value['$ref']);
            return $ids[$value['$ref']];
        }
        if(array_keys($value)===['$password'])return $password;
        foreach($value as $k=>$v)$value[$k]=$resolve($v);
        return $value;
    };
    return DB::transaction(function()use($kind,$spec,&$ids,$resolve,&$columns){
        if($kind==='p')DB::table('businesses')->where('id',77)->update(['is_schedule_visible'=>true,'schedule_last_updated_month'=>$spec['months'][2]]);
        foreach($spec['operations'][$kind] as $operation){
            $table=$operation['table'];
            $allowed=$columns[$table]??=array_flip(Schema::getColumnListing($table));
            $values=$resolve($operation['values']);
            foreach(['created_at','updated_at'] as $field)if(isset($allowed[$field])&&!array_key_exists($field,$values))$values[$field]=now()->format('Y-m-d H:i:s');
            foreach($values as $field=>$value){
                if(!isset($allowed[$field]))throw new RuntimeException('Unknown sample column: '.$table.'.'.$field);
                if(is_array($value))$values[$field]=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            }
            if(isset($allowed['id']))$id=DB::table($table)->insertGetId($values);
            else{DB::table($table)->insert($values);$id=null;}
            $ids[$operation['key']]=$values['id']??$id;
        }
        return reviewSamplesVerify($kind,$spec);
    });
}
