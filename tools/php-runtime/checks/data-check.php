<?php
use Illuminate\Support\Facades\{Artisan, Auth, DB, Storage};

try{
    $phase=$runtimePhase??($argv[1]??'');$kind=getenv('DEMO_APP');
    if(PHP_SAPI!=='cli'||getenv('MANUAL_DEMO')!=='1'||!in_array($kind,['p','u'],true)||@file_get_contents('/opt/manual-demo-marker')!=='local-manual-demo-v1'||is_file('/app/.env')||!is_file('/data/ready'))throw new RuntimeException('Guarded demo required.');
    $app=require '/demo/bootstrap.php';
    $main='/data/database.sqlite';$backup='/data/php-runtime-8.3.37-20261129-v1.before.sqlite';$record='/data/php-runtime-8.3.37-20261129-v1.json';
    $checkpoint=static function(){
        $r=DB::connection()->getPdo()->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
        if((int)$r[0]!==0)throw new RuntimeException('Database has an active writer.');
        DB::purge();
    };
    if($phase==='snapshot'){
        if(file_exists($backup)||file_exists($record))throw new RuntimeException('Previous runtime snapshot exists; do not retry.');
        if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('Original encryption check failed.');
        $checkpoint();$hash=hash_file('sha256',$main);
        if(!copy($main,$backup))throw new RuntimeException('Cannot retain database.');chmod($backup,0600);
        $report=['before_sha256'=>$hash,'old_php'=>PHP_VERSION,'password_probe'=>password_hash('runtime-security-check',PASSWORD_BCRYPT,['cost'=>4])];
        file_put_contents($record,json_encode($report,JSON_THROW_ON_ERROR));chmod($record,0600);
        $report=['snapshot'=>true,'encrypted'=>true,'php'=>PHP_VERSION];
    }elseif($phase==='restore'){
        DB::purge();$saved=json_decode(file_get_contents($record),true,512,JSON_THROW_ON_ERROR);
        if(!is_file($backup)||hash_file('sha256',$backup)!==$saved['before_sha256'])throw new RuntimeException('Original database fingerprint differs.');
        foreach([$main.'-wal',$main.'-shm'] as $path)if(is_file($path))unlink($path);
        if(!rename($backup,$main))throw new RuntimeException('Cannot restore database.');
        $report=['restored'=>true];
    }elseif($phase==='restored'){
        DB::purge();$saved=json_decode(file_get_contents($record),true,512,JSON_THROW_ON_ERROR);
        if(PHP_VERSION!==$saved['old_php']||hash_file('sha256',$main)!==$saved['before_sha256'])throw new RuntimeException('Restored runtime/database differs.');
        $report=['restored'=>true,'php'=>PHP_VERSION];
    }elseif(in_array($phase,['check','verify'],true)){
        if(PHP_VERSION!=='8.3.37')throw new RuntimeException('Patched PHP 8.3.37 required.');
        $saved=json_decode(file_get_contents($record),true,512,JSON_THROW_ON_ERROR);
        if(Artisan::call('privacy:verify')!==0||DB::connection()->getPdo()->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('Encryption/foreign keys failed after PHP update.');
        $checkpoint();
        if(($phase==='check'&&hash_file('sha256',$main)!==$saved['before_sha256'])||!password_verify('runtime-security-check',$saved['password_probe']))throw new RuntimeException('Database/password compatibility check failed.');
        $report=['php'=>PHP_VERSION,'encrypted'=>true,'database_unchanged'=>$phase==='check','password_compatible'=>true];
    }elseif($phase==='integration'){
        if($kind!=='u'||PHP_VERSION!=='8.3.37')throw new RuntimeException('Patched UCRM required.');
        $receipt=json_decode(file_get_contents('/data/review-samples.json'),true,512,JSON_THROW_ON_ERROR);
        $facility=\App\Models\Facility::whereNotNull('yoyaku_business_id')->sole();
        $grid=app(\App\Services\YoyakuApiService::class)->transportGrid('GET',['business_id'=>(int)$facility->yoyaku_business_id,'date'=>$receipt['anchor']],(int)$facility->id);
        if(!is_array($grid['rows']??null)||!count($grid['rows']))throw new RuntimeException('Transport integration failed.');
        $report=['integration'=>true,'rows'=>count($grid['rows'])];
    }elseif($phase==='pdf'){
        if($kind!=='u'||PHP_VERSION!=='8.3.37')throw new RuntimeException('Patched UCRM required.');
        require_once '/app/tools/demo-review/download-probe.php';
        $receipt=json_decode(file_get_contents('/data/review-samples.json'),true,512,JSON_THROW_ON_ERROR);$month=substr($receipt['anchor'],0,7);$ym=str_replace('-','',$month);
        $admin=\App\Models\Staff::where('facility_id',1)->where('role','admin')->sole()->user;
        Auth::guard('web')->setUser($admin);Auth::shouldUse('web');$kernel=app(\Illuminate\Contracts\Http\Kernel::class);
        foreach(['pdf-bundle'=>['PK','cap-management/cap_1_'.$ym.'.zip'],'payment-list'=>['%PDF','cap-management/beneficiary_payment_list_1_'.$ym.'.pdf']] as $route=>[$prefix,$path]){
            $request=\Illuminate\Http\Request::create('/billing/cap-management/'.$route.'?year_month='.$month,'GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);$response=$kernel->handle($request);
            if($response->getStatusCode()!==200)throw new RuntimeException('PDF HTTP check failed: '.$route.' '.$response->getStatusCode());
            \ManualDemo\probeDownloadFile(Storage::disk('local')->path($path),$prefix);$kernel->terminate($request,$response);unset($response,$request);gc_collect_cycles();
        }
        $report=['pdf'=>true];
    }else throw new RuntimeException('Unknown runtime check.');
    echo json_encode($report,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.get_class($error).': '.$error->getMessage()."\n");exit(1);}
