<?php
use Illuminate\Support\Facades\{Artisan, DB, Storage};

$app=require '/demo/bootstrap.php';
$record=json_decode(file_get_contents('/data/print-pdf-fix-v4.json'),true,512,JSON_THROW_ON_ERROR);
if(($argv[1]??'')==='restored'){
    DB::disconnect();
    if(hash_file('sha256','/data/database.sqlite')!==$record['before_sha256'])throw new RuntimeException('Original database was not restored.');
    echo "NATIVE_DATABASE_RESTORED\n";exit(0);
}
if($record['billing']['details']!==11||$record['billing']['invoices']!==11||count($record['pdf_http'])!==2||in_array(false,$record['pdf_http'],true))throw new RuntimeException('Fixture billing/PDF results differ.');
$period=\App\Models\BillingPeriod::where('facility_id',1)->where('year_month','2026-11')->sole();
app(\App\Services\Billing\BillingCompletenessGuard::class)->assertPeriod($period);
if(Artisan::call('privacy:verify')!==0)throw new RuntimeException('Fixture encryption check failed.');
$exports=0;foreach(Storage::disk('local')->allFiles('cap-management') as $path)$exports+=Storage::disk('local')->size($path);
if($exports>=8*1024*1024)throw new RuntimeException('PDF exports use too much temporary storage.');
$text=preg_replace('/\s+/u','',file_get_contents('/tmp/payment-list.txt'));
if(!str_contains($text,'利用者負担額一覧表')||!str_contains($text,'青空'))throw new RuntimeException('Subset font lost Japanese text.');
echo json_encode(['native_pdf_routes'=>true,'billing_details'=>11,'export_bytes'=>$exports,'storage_free_bytes'=>disk_free_space('/app/storage'),'japanese_text'=>true,'encrypted'=>true],JSON_THROW_ON_ERROR)."\n";
