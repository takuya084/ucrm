<?php
use Illuminate\Support\Facades\{Artisan,DB,Hash,Queue};
try {
    $kind=getenv('DEMO_APP');
    if(PHP_SAPI!=='cli'||getenv('MANUAL_DEMO')!=='1'||!in_array($kind,['p','u'],true)||@file_get_contents('/opt/manual-demo-marker')!=='local-manual-demo-v1'||is_file('/app/.env'))throw new RuntimeException('private_demo_seed_refused');
    $file=fopen('/data/database.sqlite','x');if(!$file)throw new RuntimeException('fresh_demo_database_required');fclose($file);chmod('/data/database.sqlite',0600);
    $app=require __DIR__.'/bootstrap.php';
    if(Artisan::call('migrate',['--force'=>true])!==0)throw new RuntimeException('private_demo_schema_failed');
    Queue::fake();
    DB::statement('PRAGMA journal_mode=WAL');
    DB::transaction(function()use($kind){
        $password=Hash::make('Demo-local-2026!');
        if($kind==='p'){
            $secret=bin2hex(random_bytes(32));
            \App\Models\Business::forceCreate(['id'=>77,'name'=>'【テスト】事業所','billing_type'=>'free','is_active'=>true,'pickup_start'=>'09:00','dropoff_end'=>'18:00','holiday_weekdays'=>['Sunday'],'reservation_deadline_day'=>31,'webhook_url'=>'http://u:8000/api/webhooks/yoyaku','webhook_secret'=>$secret]);
            $user=\App\Models\User::forceCreate(['name'=>'【テスト】管理者','email'=>'admin-a@example.test','person_type'=>'teacher','access_role'=>'admin','business_id'=>77,'password'=>$password,'email_verified_at'=>now()]);
            $tokens=['token'=>$user->createToken('manual-demo')->plainTextToken,'secret'=>$secret];
            $out=fopen('/link/tokens.json','x');if(!$out)throw new RuntimeException('fresh_link_volume_required');chmod('/link/tokens.json',0600);fwrite($out,json_encode($tokens,JSON_THROW_ON_ERROR));fclose($out);
        }else{
            $tokens=json_decode(file_get_contents('/link/tokens.json'),true,512,JSON_THROW_ON_ERROR);
            $facility=\App\Models\Facility::create(['name'=>'【テスト】事業所','capacity_per_day'=>10,'billing_type'=>'free','is_active'=>true,'yoyaku_business_id'=>77,'yoyaku_api_token'=>$tokens['token'],'yoyaku_webhook_secret'=>$tokens['secret']]);
            $user=\App\Models\User::forceCreate(['name'=>'【テスト】管理者','email'=>'admin-a@example.test','password'=>$password,'email_verified_at'=>now()]);
            \App\Models\Staff::create(['facility_id'=>$facility->id,'user_id'=>$user->id,'name'=>$user->name,'role'=>'admin','is_active'=>true]);
        }
    });
    if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('private_database_verification_failed');
    file_put_contents('/data/summary.json',json_encode(['demo'=>true,'app'=>$kind,'children'=>0,'mode'=>'fresh-encrypted'],JSON_THROW_ON_ERROR));
    file_put_contents('/data/ready','manual-demo-v1');
    echo "PASS: fresh encrypted database and administrator created.\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.get_class($error).': '.$error->getMessage()."\n");exit(1);}
