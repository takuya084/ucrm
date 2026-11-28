<?php
// Disposable CI validation; never points to an existing database or .env.
if(getenv('GITHUB_ACTIONS')!=='true'||PHP_SAPI!=='cli')exit(2);
$kind=$argv[1];$root=realpath($argv[2]);$kit=__DIR__;
if(!in_array($kind,['p','u'],true)||is_file($root.'/.env'))exit(2);
require $root.'/vendor/autoload.php';
$file=tempnam(getenv('RUNNER_TEMP'),'review-'.$kind.'-');chmod($file,0600);
$app=require $root.'/bootstrap/app.php';
$app->useEnvironmentPath('/nonexistent-env');$app->loadEnvironmentFrom('none');
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,function($app)use($file){
    $app->detectEnvironment(fn()=>'testing');
    $app->make('config')->set(['app.key'=>'base64:'.base64_encode(random_bytes(32)),'app.env'=>'testing','app.debug'=>false,
        'database.default'=>'sqlite','database.connections.sqlite.database'=>$file,'database.connections.sqlite.foreign_key_constraints'=>true,
        'cache.default'=>'array','session.driver'=>'array','mail.default'=>'array','queue.default'=>'sync','logging.default'=>'stderr']);
});
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(\Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true])!==0)throw new RuntimeException('CI migration failed');
\Illuminate\Support\Facades\Queue::fake();\Illuminate\Support\Facades\Mail::fake();\Illuminate\Support\Facades\Notification::fake();
$password=\Illuminate\Support\Facades\Hash::make('Demo-local-2026!');
if($kind==='p'){
    \App\Models\Business::forceCreate(['id'=>77,'name'=>'【テスト】事業所','billing_type'=>'free','is_active'=>true,'pickup_start'=>'09:00','dropoff_end'=>'18:00','holiday_weekdays'=>['Sunday'],'reservation_deadline_day'=>31]);
    \App\Models\User::forceCreate(['name'=>'【テスト】管理者','email'=>'admin-a@example.test','password'=>$password,'person_type'=>'teacher','access_role'=>'admin','business_id'=>77,'email_verified_at'=>now()]);
}else{
    $facility=\App\Models\Facility::create(['name'=>'【テスト】事業所','billing_type'=>'free','is_active'=>true,'capacity_per_day'=>10,'yoyaku_business_id'=>77]);
    $user=\App\Models\User::forceCreate(['name'=>'【テスト】管理者','email'=>'admin-a@example.test','password'=>$password,'email_verified_at'=>now()]);
    \App\Models\Staff::create(['facility_id'=>$facility->id,'user_id'=>$user->id,'name'=>$user->name,'role'=>'admin','is_active'=>true]);
}
require $kit.'/engine.php';
$spec=json_decode(file_get_contents($kit.'/spec.json'),true,512,JSON_THROW_ON_ERROR);
$report=reviewSamplesPopulate($kind,$spec);
$hash=hash_file('sha256',$file);
try{reviewSamplesPopulate($kind,$spec);throw new RuntimeException('Second insert was not refused');}
catch(RuntimeException $error){if(!str_starts_with($error->getMessage(),'Existing records found'))throw $error;}
if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('Second insert modified existing database');
$report['second_insert_refused']=true;
file_put_contents(getenv('RUNNER_TEMP').'/review-'.$kind.'-summary.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'PASS '.$kind.': encrypted sample rows, valid references, model relations and duplicate refusal'.PHP_EOL;
