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
                foreach (['mon','tue','wed','thu','fri','sat','sun'] as $day) {
                    $child->schedules()->create(['day_of_week'=>$day,'start_date'=>'2026-01-01','status'=>'regular']);
                }
            }
        }
    }
} elseif ($command !== 'snapshot') {
    throw new RuntimeException('fixture_command_refused');
}
// All values belong to synthetic data in the private CI database.
echo json_encode([
    'facilities'=>Facility::orderBy('id')->get(['id','name']),
    'staff'=>Staff::orderBy('id')->get(['id','facility_id','name','role','is_active']),
    'schools'=>School::orderBy('id')->get(['id','facility_id','name']),
    'children'=>Child::withTrashed()->orderBy('id')->get(['id','facility_id','school_id','name','birthdate','contract_start_date','contract_status','deleted_at']),
    'schedules'=>\App\Models\ChildSchedule::orderBy('id')->get(['child_id','day_of_week']),
    'usage'=>UsageRecord::withTrashed()->orderBy('id')->get(['id','child_id','facility_id','status','date','deleted_at']),
    'support'=>SupportRecord::orderBy('id')->get(['id','child_id','usage_record_id','staff_id','behavior_note']),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
