<?php
use Illuminate\Support\Facades\{Artisan, Auth, DB, Queue, Storage};

try {
    if(PHP_SAPI!=='cli'||getenv('DEMO_APP')!=='u'||getenv('MANUAL_DEMO')!=='1'||@file_get_contents('/opt/manual-demo-marker')!=='local-manual-demo-v1'||is_file('/app/.env')||!is_file('/data/ready'))throw new RuntimeException('デモ専用の修正処理です。');
    $app=require '/demo/bootstrap.php';
    require_once '/app/tools/demo-review/download-probe.php';
    $phase=$argv[1]??'';
    $main='/data/database.sqlite';$stage='/data/print-pdf-fix-v3.staging.sqlite';$before='/data/print-pdf-fix-v3.before.sqlite';$record='/data/print-pdf-fix-v3.json';
    $checkpoint=static function(){
        $r=DB::connection()->getPdo()->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
        if((int)$r[0]!==0)throw new RuntimeException('DBの書込みが完了していません。');
        DB::disconnect();
    };
    $pdfCheck=static function(int $facilityId,string $month):array{
        $admin=\App\Models\Staff::where('facility_id',$facilityId)->where('role','admin')->sole()->user;
        Auth::guard('web')->setUser($admin);Auth::shouldUse('web');
        $kernel=app(\Illuminate\Contracts\Http\Kernel::class);$result=[];
        $ym=str_replace('-','',$month);
        $files=['pdf-bundle'=>['PK',"cap-management/cap_{$facilityId}_{$ym}.zip"],
            'payment-list'=>['%PDF',"cap-management/beneficiary_payment_list_{$facilityId}_{$ym}.pdf"]];
        foreach($files as $route=>[$prefix,$path]){
            $request=\Illuminate\Http\Request::create('/billing/cap-management/'.$route.'?year_month='.$month,'GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);
            $response=$kernel->handle($request);
            if($response->getStatusCode()!==200)throw new RuntimeException('PDF出力のHTTP確認に失敗しました: '.$route.' '.$response->getStatusCode());
            \ManualDemo\probeDownloadFile(Storage::disk('local')->path($path),$prefix);
            $kernel->terminate($request,$response);
            unset($response,$request);gc_collect_cycles();
            $result[$route]=true;
        }
        return $result;
    };
    if($phase==='prepare'){
        if(file_exists('/data/print-pdf-fix.before.sqlite'))throw new RuntimeException('以前のDB切替記録があるため停止します。');
        if(file_exists('/data/print-pdf-fix-v2.before.sqlite'))throw new RuntimeException('v2のDB切替記録があるため停止します。');
        foreach([$stage,$before,$record] as $path)if(file_exists($path)||is_link($path))throw new RuntimeException('修正処理の途中記録があります。再実行しないでください。');
        $receipt=json_decode(file_get_contents('/data/review-samples.json'),true,512,JSON_THROW_ON_ERROR);
        if(($receipt['version']??null)!=='review-samples-20261128-v2')throw new RuntimeException('v2テストデータの投入完了を確認できません。');
        $month=substr($receipt['anchor'],0,7);
        if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('現在のDBの暗号化を確認できません。');
        $checkpoint();$original=hash_file('sha256',$main);
        if(!copy($main,$stage))throw new RuntimeException('一時DBを作成できません。');chmod($stage,0600);
        config(['database.connections.sqlite.database'=>$stage]);DB::purge();Queue::fake();
        require '/app/tools/demo-review/repair-billing.php';
        $billing=repairReviewBillingSamples(1,$month);
        $outputs=$pdfCheck(1,$month);
        if(Artisan::call('privacy:verify')!==0||DB::connection()->getPdo()->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('修正後の暗号化・参照関係を確認できません。');
        $checkpoint();$report=['before_sha256'=>$original,'staged_sha256'=>hash_file('sha256',$stage),'billing'=>$billing,'pdf_http'=>$outputs];
        file_put_contents($record,json_encode($report,JSON_THROW_ON_ERROR));chmod($record,0600);
    }elseif($phase==='commit'){
        DB::disconnect();$report=json_decode(file_get_contents($record),true,512,JSON_THROW_ON_ERROR);
        if(file_exists($before)||hash_file('sha256',$main)!==$report['before_sha256']||hash_file('sha256',$stage)!==$report['staged_sha256'])throw new RuntimeException('切替前のDBが一致しません。');
        foreach([$main.'-wal',$stage.'-wal'] as $path)if(is_file($path)&&filesize($path)!==0)throw new RuntimeException('未完了のDB書込みがあります。');
        foreach([$main.'-wal',$main.'-shm',$stage.'-wal',$stage.'-shm'] as $path)if(is_file($path))unlink($path);
        if(!copy($main,$before))throw new RuntimeException('元DBを保持できません。');chmod($before,0600);
        if(!rename($stage,$main)){unlink($before);throw new RuntimeException('修正済みDBへ切り替えられません。');}
    }elseif($phase==='undo'){
        DB::disconnect();
        if(!is_file($before)){$report=['returned'=>false];}
        else{
            $saved=json_decode(file_get_contents($record),true,512,JSON_THROW_ON_ERROR);
            if(hash_file('sha256',$before)!==$saved['before_sha256'])throw new RuntimeException('元DBの照合に失敗しました。');
            foreach([$main.'-wal',$main.'-shm'] as $path)if(is_file($path))unlink($path);
            if(!rename($before,$main))throw new RuntimeException('元DBへ戻せません。');
            $report=['returned'=>true];
        }
    }elseif($phase==='check'){
        $saved=json_decode(file_get_contents($record),true,512,JSON_THROW_ON_ERROR);$month=$saved['billing']['month'];
        $report=['pdf_http'=>$pdfCheck(1,$month)];
        $period=\App\Models\BillingPeriod::where('facility_id',1)->where('year_month',$month)->sole();
        app(\App\Services\Billing\BillingCompletenessGuard::class)->assertPeriod($period);
        if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('DBの暗号化を確認できません。');
        $grid=app(\App\Services\YoyakuApiService::class)->transportGrid('GET',['business_id'=>77,'date'=>$saved['billing']['month'].'-02'],1);
        $report['integration']=is_array($grid['rows']??null);$report['encrypted']=true;
    }else throw new RuntimeException('不明な確認処理です。');
    echo json_encode($report,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.get_class($error).': '.$error->getMessage()."\n");exit(1);}
