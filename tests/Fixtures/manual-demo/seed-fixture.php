<?php
use Illuminate\Support\Facades\{Artisan, DB, Queue};

if(file_exists('/data/database.sqlite'))throw new RuntimeException('Fresh fixture database required.');
touch('/data/database.sqlite');
$app=require '/demo/bootstrap.php';
if(Artisan::call('migrate',['--force'=>true])!==0)throw new RuntimeException('Fixture migration failed.');
Queue::fake();DB::statement('PRAGMA journal_mode=WAL');
$facility=\App\Models\Facility::create(['name'=>'【テスト】事業所','capacity_per_day'=>10,'area_unit_price'=>10,'billing_type'=>'free','is_active'=>true,'yoyaku_business_id'=>77]);
$user=\App\Models\User::forceCreate(['name'=>'【テスト】管理者','email'=>'admin-a@example.test','password'=>bcrypt('Demo-local-2026!'),'email_verified_at'=>now()]);
\App\Models\Staff::create(['facility_id'=>$facility->id,'user_id'=>$user->id,'name'=>$user->name,'role'=>'admin','is_active'=>true]);
require '/app/tests/Fixtures/review-data-engine.php';
$spec=json_decode(file_get_contents('/demo/dataset.json'),true,512,JSON_THROW_ON_ERROR);
reviewSamplesPopulate('u',$spec);
file_put_contents('/data/review-samples.json',json_encode(['version'=>$spec['version'],'anchor'=>$spec['anchor']],JSON_THROW_ON_ERROR));
file_put_contents('/data/ready','manual-demo-v1');
echo "NATIVE_FIXTURE_READY\n";
