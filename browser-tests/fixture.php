<?php

if (PHP_SAPI !== 'cli') throw new RuntimeException('fixture_cli_only');
$app = require __DIR__.'/bootstrap.php';
use App\Models\{Facility, User, Staff, School, Child, UsageRecord, SupportRecord};
use Illuminate\Support\Facades\{Artisan, Hash};

$command = $argv[1] ?? '';
if ($command === 'reset') {
    // Guarded bootstrap forces this freshly allocated CI-only SQLite file.
    Artisan::call('migrate:fresh', ['--force'=>true]);
    foreach (['a','b'] as $tenant) {
        $facility = Facility::create(['name'=>'架空事業所'.$tenant,'capacity_per_day'=>10,'billing_type'=>'free','is_active'=>true]);
        foreach (['admin','leader','staff','inactive'] as $role) {
            $user = User::factory()->create([
                'name'=>'架空職員'.$tenant.'-'.$role, 'email'=>$role.'-'.$tenant.'@example.test',
                'password'=>Hash::make('Fixture-password-123!'),
            ]);
            Staff::create(['facility_id'=>$facility->id,'user_id'=>$user->id,'name'=>$user->name,
                'role'=>$role==='inactive'?'staff':$role,'is_active'=>$role!=='inactive']);
        }
        $school = School::create(['facility_id'=>$facility->id,'name'=>'架空学校'.$tenant,'school_type'=>'elementary']);
        foreach (['active','ended'] as $status) {
            $child = Child::create(['facility_id'=>$facility->id,'school_id'=>$school->id,
                'name'=>'架空児童'.$tenant.'-'.$status,'name_kana'=>'カクウジドウ',
                'birthdate'=>'2017-04-02','contract_start_date'=>'2026-04-01',
                'contract_status'=>$status,'allergy_note'=>'架空の配慮情報']);
            if ($status==='active') {
                foreach (['mon','tue','wed','thu','fri','sat'] as $day) {
                    $child->schedules()->create(['day_of_week'=>$day,'start_date'=>'2026-01-01','status'=>'regular']);
                }
            }
        }
    }
 } elseif ($command === 'transport-summary') {
    $own=Child::where('name','架空児童a-active')->firstOrFail();
    $staff=Staff::where('facility_id',$own->facility_id)->where('role','staff')->firstOrFail();
    $assistant=Staff::where('facility_id',$own->facility_id)->where('role','leader')->firstOrFail();
    $second=Child::create(['facility_id'=>$own->facility_id,'school_id'=>$own->school_id,'name'=>'同乗の第二児童','contract_status'=>'active']);
    foreach ([$own,$second] as $child) {
        UsageRecord::create(['facility_id'=>$child->facility_id,'child_id'=>$child->id,'date'=>'2026-07-01','status'=>'attended']);
        foreach (['pickup','dropoff'] as $direction) {
            \Illuminate\Support\Facades\DB::table('daily_transport_assignments')->insert([
                'facility_id'=>$child->facility_id,'child_id'=>$child->id,'staff_id'=>$staff->id,'assistant_staff_id'=>$assistant->id,
                'service_date'=>'2026-07-01','direction'=>$direction,'scheduled_time'=>$direction==='pickup'?'14:30':'17:30',
                'location'=>$direction==='pickup'?'学校':'自宅','created_at'=>now(),'updated_at'=>now()]);
        }
    }
 } elseif ($command === 'attendance-roster') {
    $own=Child::where('name','架空児童a-active')->firstOrFail();
    foreach (['第二児童','第三児童'] as $name) {
        Child::create(['facility_id'=>$own->facility_id,'school_id'=>$own->school_id,
            'name'=>$name,'name_kana'=>$name,'contract_status'=>'active','allergy_note'=>'卵アレルギー']);
    }
    foreach (Child::where('contract_status','active')->get() as $child) {
        UsageRecord::create(['facility_id'=>$child->facility_id,'child_id'=>$child->id,
            'date'=>'2026-12-02','status'=>'attended','memo'=>'元の記録']);
    }
} elseif ($command === 'legacy-attendance-times') {
    $child=Child::where('name','架空児童a-active')->firstOrFail();
    UsageRecord::create(['facility_id'=>$child->facility_id,'child_id'=>$child->id,
        'date'=>'2026-07-01','status'=>'attended','check_in_time'=>'14:31','check_out_time'=>'17:33']);
 } elseif ($command === 'morning-entry') {
    $child=Child::where('name','架空児童a-active')->firstOrFail();
    $note=new \App\Models\ContactNote(['facility_id'=>$child->facility_id,'child_id'=>$child->id,'date'=>'2026-07-01',
        'guardian_submitted_at'=>now(),'guardian_comment'=>'架空の利用前の体調連絡','home_temperature'=>'36.6']);
    $note->guardian_source_revision=1; $note->save();
 } elseif (in_array($command, ['school-calendar', 'school-calendar-bulk'], true)) {
    $child=Child::where('name','架空児童a-active')->firstOrFail();
    \App\Models\RecipientCertificate::create(['child_id'=>$child->id,'status'=>'active','valid_from'=>'2026-01-01','valid_to'=>'2026-12-31','service_type'=>'houday','certificate_number'=>'1234567890']);
    $younger=Child::create(['facility_id'=>$child->facility_id,'name'=>'架空の児発児童','contract_status'=>'active']);
    \App\Models\RecipientCertificate::create(['child_id'=>$younger->id,'status'=>'active','valid_from'=>'2026-01-01','valid_to'=>'2026-12-31','service_type'=>'jidou','certificate_number'=>'1234567891']);
    if ($command === 'school-calendar-bulk') {
        $other=Child::create(['facility_id'=>$child->facility_id,'name'=>'架空の一括設定対象外児童','contract_status'=>'active']);
        \App\Models\RecipientCertificate::create(['child_id'=>$other->id,'status'=>'active','valid_from'=>'2026-01-01','valid_to'=>'2026-12-31','service_type'=>'houday','certificate_number'=>'1234567892']);

    }
} elseif ($command === 'school-calendar-concurrent') {
    $child=Child::where('name','架空児童a-active')->firstOrFail();
    \Illuminate\Support\Facades\DB::table('school_calendar_months')->insert(['facility_id'=>$child->facility_id,'month'=>'2026-07','revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
    \Illuminate\Support\Facades\DB::table('child_school_days')->insert(['facility_id'=>$child->facility_id,'child_id'=>$child->id,'date'=>'2026-07-01','is_school_day'=>false,'created_at'=>now(),'updated_at'=>now()]);
 } elseif ($command === 'contact-delivery') {
    $facility=Facility::where('name','架空事業所a')->firstOrFail();
    $facility->update(['yoyaku_business_id'=>77,'yoyaku_api_token'=>'synthetic-private-token']);
    $child=Child::where('name','架空児童a-active')->firstOrFail();$child->update(['yoyaku_user_id'=>501]);
    $note=\App\Models\ContactNote::create(['facility_id'=>$facility->id,'child_id'=>$child->id,'date'=>'2026-07-01',
        'status'=>'published','published_at'=>now(),'guardian_message'=>'架空の公開連絡帳']);
    $note->forceFill(['delivery_error_code'=>'authentication'])->save();
} elseif ($command === 'contact-delivery-complete') {
    \Illuminate\Support\Facades\Http::fake(['*'=>\Illuminate\Support\Facades\Http::response(['ok'=>true])]);
    $note=\App\Models\ContactNote::where('guardian_message','架空の公開連絡帳')->firstOrFail();
    (new \App\Jobs\PushContactNote($note->id))->handle(app(\App\Services\YoyakuApiService::class));
} elseif ($command === 'contact-notes') {
    foreach (['a','b'] as $tenant) {
        $child=Child::where('name','架空児童'.$tenant.'-active')->firstOrFail();
        \App\Models\ContactNote::create(['facility_id'=>$child->facility_id,'child_id'=>$child->id,'date'=>'2026-07-01',
            'status'=>'published','published_at'=>now(),'guardian_message'=>'架空の公開連絡帳','guardian_comment'=>'架空の家庭返信']);
    }
} elseif ($command === 'no-special-notes') {
    $own=Child::where('name','架空児童a-active')->firstOrFail();
    $own->update(['allergy_note'=>'【架空】特記事項なし']);
    Child::create(['facility_id'=>$own->facility_id,'name'=>'注意児童','name_kana'=>'チュウイ','contract_status'=>'active','allergy_note'=>'卵アレルギー']);
    foreach (Child::where('facility_id',$own->facility_id)->where('contract_status','active')->get() as $child) {
        UsageRecord::create(['facility_id'=>$child->facility_id,'child_id'=>$child->id,'date'=>'2026-07-01','status'=>'attended']);
    }
} elseif ($command === 'daily-activity') {
    $facility=Facility::where('name','架空事業所a')->firstOrFail();
    $activity = new \App\Models\DailyActivityPlan(['activity_date'=>'2026-07-01','category'=>'support','title'=>'架空の読み聞かせ','materials'=>'絵本']);
    $activity->facility_id = $facility->id;
    $activity->save();
} elseif ($command === 'shift-demo') {
    $demoResult=app(\App\Support\ShiftDemoData::class)->create('2026-12','Fixture-password-123!');
} elseif ($command !== 'snapshot') {
    throw new RuntimeException('fixture_command_refused');
}
// All values belong to synthetic data in the private CI database.
echo json_encode([
    'shift_demo'=>$demoResult??null,
    'facilities'=>Facility::orderBy('id')->get(['id','name']),
    'staff'=>Staff::orderBy('id')->get(['id','facility_id','name','role','is_active']),
    'schools'=>School::orderBy('id')->get(['id','facility_id','name']),
    'children'=>Child::withTrashed()->orderBy('id')->get(['id','facility_id','school_id','name','birthdate','contract_start_date','contract_status','deleted_at']),
    'schedules'=>\App\Models\ChildSchedule::orderBy('id')->get(['child_id','day_of_week']),
    'usage'=>UsageRecord::withTrashed()->orderBy('id')->get(['id','child_id','facility_id','status','date','deleted_at','check_in_time','check_out_time']),
    'support'=>SupportRecord::orderBy('id')->get(['id','child_id','usage_record_id','staff_id','behavior_note']),
    'notes'=>\App\Models\ContactNote::orderBy('id')->get(['id','facility_id','child_id','status','withdrawn_at','published_at','guardian_message','guardian_comment','guardian_checked_at','guardian_checked_revision','guardian_confirmed_at']),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
