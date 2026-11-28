<?php
use Illuminate\Support\Facades\Queue;
$app=require '/demo/bootstrap.php';Queue::fake();
require '/app/tests/Fixtures/review-data-engine.php';
$spec=json_decode(file_get_contents('/demo/dataset.json'),true,512,JSON_THROW_ON_ERROR);
$report=reviewSamplesPopulate(getenv('DEMO_APP'),$spec);
file_put_contents('/data/review-samples.json',json_encode(['version'=>$spec['version'],'anchor'=>$spec['anchor']],JSON_THROW_ON_ERROR));
echo json_encode(['populated'=>true,'kind'=>getenv('DEMO_APP'),'encrypted'=>$report['encrypted']],JSON_THROW_ON_ERROR)."\n";
