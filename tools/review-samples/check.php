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

// Reproduce the Mac schema constraint even when an older SQLite schema rewrite
// has weakened nullability in a fresh CI database.
if($kind==='u'){
    \Illuminate\Support\Facades\DB::unprepared("CREATE TRIGGER review_school_day_required BEFORE INSERT ON usage_records WHEN NEW.is_school_day IS NULL BEGIN SELECT RAISE(ABORT,'NOT NULL constraint failed: usage_records.is_school_day'); END");
    $bad=$spec;
    foreach($bad['operations']['u'] as &$operation)if($operation['table']==='usage_records'&&$operation['values']['child_id']===1014)$operation['values']['is_school_day']=null;
    unset($operation);
    $reproduced=false;
    try{reviewSamplesPopulate($kind,$bad);}
    catch(\Illuminate\Database\QueryException $error){if(!str_contains($error->getMessage(),'usage_records.is_school_day'))throw $error;$reproduced=true;}
    if(!$reproduced||\App\Models\Child::count()!==0||\App\Models\User::count()!==1)throw new RuntimeException('Original NOT NULL failure was not reproduced or transaction was not rolled back');
    echo 'PASS: original Mac NOT NULL error reproduced; original rows preserved'.PHP_EOL;
}

// Retain a fresh baseline for the exact updater staging/activation test.
$baseline=$file.'.baseline';\Illuminate\Support\Facades\DB::disconnect();copy($file,$baseline);
$report=reviewSamplesPopulate($kind,$spec);
$hash=hash_file('sha256',$file);
try{reviewSamplesPopulate($kind,$spec);throw new RuntimeException('Second insert was not refused');}
catch(RuntimeException $error){if(!str_starts_with($error->getMessage(),'Existing records found'))throw $error;}
if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('Second insert modified existing database');
$report['second_insert_refused']=true;$report['mac_school_day_constraint_reproduced_and_fixed']=($kind==='u');
file_put_contents(getenv('RUNNER_TEMP').'/review-'.$kind.'-summary.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'PASS '.$kind.': encrypted sample rows, valid references, model relations and duplicate refusal'.PHP_EOL;

$deploymentRuntime=<<<'REVIEW_RUNTIME_SOURCE'
<?php
// This script is sent through stdin to an existing guarded manual-demo image.
// The Python launcher provides $sampleSpec, $samplePhase and the engine below.
try {
    $kind=getenv('DEMO_APP');
    if(PHP_SAPI!=='cli'||getenv('MANUAL_DEMO')!=='1'||!in_array($kind,['p','u'],true)||@file_get_contents('/opt/manual-demo-marker')!=='local-manual-demo-v1'||is_file('/app/.env')||!is_file('/data/ready'))throw new RuntimeException('Review sample runner refused');
    $app=require '/demo/bootstrap.php';
    if($sampleSpec['version']!=='review-samples-20261128-v2')throw new RuntimeException('Wrong sample version');
    $main='/data/database.sqlite';$stage='/data/review-samples-v2.staging.sqlite';$before='/data/review-samples-v2.before.sqlite';$receipt='/data/review-samples.json';$prepared='/data/review-samples-v2.prepared.json';
    $checkpoint=static function(){
        $result=DB::connection()->getPdo()->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
        if((int)$result[0]!==0)throw new RuntimeException('Database has an active writer');
        DB::disconnect();
    };
    if($samplePhase==='prepare'){
        foreach([$stage,$before,$receipt,$prepared] as $path)if(file_exists($path)||is_link($path))throw new RuntimeException('Sample preparation already exists; do not retry');
        reviewSamplesEmpty($kind,$sampleSpec);
        if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('Baseline encryption check failed');
        $checkpoint();$original=hash_file('sha256',$main);
        if(!copy($main,$stage))throw new RuntimeException('Unable to create sample staging database');chmod($stage,0600);
        config(['database.connections.sqlite.database'=>$stage]);DB::purge();
        $report=reviewSamplesPopulate($kind,$sampleSpec);
        $checkpoint();$report['before_sha256']=$original;$report['staged_sha256']=hash_file('sha256',$stage);
        file_put_contents($prepared,json_encode($report,JSON_THROW_ON_ERROR),LOCK_EX);chmod($prepared,0600);
    }elseif($samplePhase==='commit'){
        DB::disconnect();$report=json_decode(file_get_contents($prepared),true,512,JSON_THROW_ON_ERROR);
        if(file_exists($before)||file_exists($receipt)||hash_file('sha256',$main)!==$report['before_sha256']||hash_file('sha256',$stage)!==$report['staged_sha256'])throw new RuntimeException('Sample activation fingerprint mismatch');
        if((is_file($main.'-wal')&&filesize($main.'-wal')!==0)||(is_file($stage.'-wal')&&filesize($stage.'-wal')!==0))throw new RuntimeException('Uncheckpointed database detected');
        foreach([$main.'-wal',$main.'-shm',$stage.'-wal',$stage.'-shm'] as $sidecar)if(is_file($sidecar))unlink($sidecar);
        if(!copy($main,$before))throw new RuntimeException('Unable to retain previous database');chmod($before,0600);
        if(!rename($stage,$main)){unlink($before);throw new RuntimeException('Unable to activate sample database');}
        if(!rename($prepared,$receipt)){rename($before,$main);throw new RuntimeException('Unable to activate sample receipt');}
    }elseif($samplePhase==='undo'){
        DB::disconnect();
        if(!is_file($before)){$report=['restored'=>false];echo json_encode($report)."\n";exit(0);}
        $report=json_decode(file_get_contents(is_file($receipt)?$receipt:$prepared),true,512,JSON_THROW_ON_ERROR);
        if(hash_file('sha256',$before)!==$report['before_sha256'])throw new RuntimeException('Previous database fingerprint mismatch');
        foreach([$main.'-wal',$main.'-shm'] as $sidecar)if(is_file($sidecar))unlink($sidecar);
        if(!rename($before,$main))throw new RuntimeException('Unable to return to previous database');
        if(is_file($receipt))rename($receipt,$prepared);
    }elseif($samplePhase==='check'){
        $report=reviewSamplesVerify($kind,$sampleSpec,false);
    }elseif($samplePhase==='link'){
        if($kind!=='u')throw new RuntimeException('UCRM link phase only');
        \Illuminate\Support\Facades\Queue::fake();
        $api=app(\App\Services\YoyakuApiService::class);
        $grid=$api->transportGrid('GET',['business_id'=>77,'date'=>$sampleSpec['anchor']],1);
        if(!count($grid['rows']??[]))throw new RuntimeException('Sample transport integration is empty');
        foreach($sampleSpec['delivery_notes'] as $id){
            $note=\App\Models\ContactNote::findOrFail($id);
            \App\Services\ContactNoteDelivery::enqueue($note);
            (new \App\Jobs\PushContactNote($id))->handle($api);
            if(!\App\Models\ContactNote::findOrFail($id)->yoyaku_synced_at)throw new RuntimeException('Sample contact note integration failed');
        }
        $report=['integration'=>true,'bookings'=>count($grid['rows']),'contact_notes_delivered'=>count($sampleSpec['delivery_notes'])];
    }elseif($samplePhase==='family'){
        if($kind!=='p')throw new RuntimeException('p-yoyaku family phase only');
        $tested=[];
        foreach(array_slice($sampleSpec['delivery_notes'],0,2) as $sourceId){
            $note=\App\Models\ContactNote::where('external_ref','ucrm:'.$sourceId)->sole();
            if(!$note->isPublicNow()||str_contains(json_encode($note->toArray(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'【内部専用】'))throw new RuntimeException('Sample public content check failed');
            $note->forceFill(['read_at'=>now(),'home_temperature'=>'36.5','home_sleep'=>'21:00〜06:30','guardian_comment'=>'【架空】家庭からの連絡と双方向連携の確認です。','guardian_submitted_at'=>now(),'guardian_revision'=>(int)$note->guardian_revision+1])->save();
            (new \App\Jobs\SendContactNoteWebhook((int)$note->id,'contact_note.read'))->handle();
            (new \App\Jobs\SendContactNoteWebhook((int)$note->id,'contact_note.guardian_entry'))->handle();
            if(!$note->publication_hash)throw new RuntimeException('Published sample hash missing');
            $note->forceFill(['guardian_confirmed_at'=>now(),'guardian_confirmed_hash'=>$note->publication_hash])->save();
            (new \App\Jobs\SendContactNoteWebhook((int)$note->id,'contact_note.confirmed'))->handle();
            $tested[]=$sourceId;
        }
        $report=['guardian_webhooks'=>true,'contact_note_ids'=>$tested];
    }elseif($samplePhase==='familycheck'){
        if($kind!=='u')throw new RuntimeException('UCRM family verification only');
        foreach(array_slice($sampleSpec['delivery_notes'],0,2) as $sourceId){
            $note=\App\Models\ContactNote::findOrFail($sourceId);
            if(!$note->read_at||!$note->guardian_submitted_at||!$note->guardian_confirmed_at||$note->guardian_confirmed_hash!==$note->publication_hash||$note->guardian_comment!=='【架空】家庭からの連絡と双方向連携の確認です。')throw new RuntimeException('Sample guardian webhook not reflected in UCRM');
        }
        $report=['guardian_input_read_and_confirmation'=>true];
    }else throw new RuntimeException('Unknown sample phase');
    echo json_encode($report,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.get_class($error).': '.$error->getMessage()."\n");exit(1);}

REVIEW_RUNTIME_SOURCE;

$dir=getenv('RUNNER_TEMP').'/review-staging-'.$kind;
mkdir($dir,0700);mkdir($dir.'/data',0700);mkdir($dir.'/demo',0700);
copy($baseline,$dir.'/data/database.sqlite');file_put_contents($dir.'/data/ready','ready');file_put_contents($dir.'/marker','local-manual-demo-v1');
$boot="<?php\nrequire ".var_export($root.'/vendor/autoload.php',true).";\n\$app=require ".var_export($root.'/bootstrap/app.php',true).";\n\$app->useEnvironmentPath('/nonexistent-env');\$app->loadEnvironmentFrom('none');\n";
$settings=['app.key'=>config('app.key'),'app.env'=>'testing','app.debug'=>false,'database.default'=>'sqlite','database.connections.sqlite.database'=>$dir.'/data/database.sqlite','database.connections.sqlite.foreign_key_constraints'=>true,'cache.default'=>'array','session.driver'=>'array','mail.default'=>'array','queue.default'=>'sync','logging.default'=>'stderr'];
$boot.=' $app->afterBootstrapping(\\Illuminate\\Foundation\\Bootstrap\\LoadConfiguration::class,function($app){$app->detectEnvironment(fn()=>"testing");$app->make("config")->set('.var_export($settings,true).');}); $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); return $app;';
file_put_contents($dir.'/demo/bootstrap.php',$boot);
// Relocate only paths in the test copy. The delivered runner retains its strict /data and marker guards.
$testRuntime=str_replace(["'/data/","'/opt/manual-demo-marker'","'/app/.env'","'/demo/bootstrap.php'"],["'".$dir.'/data/',var_export($dir.'/marker',true),var_export($root.'/.env',true),var_export($dir.'/demo/bootstrap.php',true)],$deploymentRuntime);
$source=file_get_contents($kit.'/engine.php');
$phase=function(string $phase)use($kind,$spec,$dir,$source,$testRuntime){
    $script='<?php $sampleSpec='.var_export($spec,true).'; $samplePhase='.var_export($phase,true).';'.substr($source,5).substr($testRuntime,5);
    file_put_contents($dir.'/phase.php',$script);
    $pipes=[];$env=array_merge(getenv(),['DEMO_APP'=>$kind,'MANUAL_DEMO'=>'1']);
    $process=proc_open([PHP_BINARY,$dir.'/phase.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
    if($code!==0)throw new RuntimeException('Staging test '.$phase.' failed: '.$err.$out);
    return json_decode($out,true,512,JSON_THROW_ON_ERROR);
};
$original=hash_file('sha256',$dir.'/data/database.sqlite');$phase('prepare');
if(hash_file('sha256',$dir.'/data/database.sqlite')!==$original)throw new RuntimeException('Preparation changed original DB');
$phase('commit');$phase('check');$phase('undo');
if(hash_file('sha256',$dir.'/data/database.sqlite')!==$original)throw new RuntimeException('Return changed original DB');
echo 'PASS '.$kind.': delivered runner preparation, activation and return to original encrypted DB'.PHP_EOL;
