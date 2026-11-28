<?php

require ($argv[1]??dirname(__DIR__,2)).'/vendor/autoload.php';
require ($argv[1]??dirname(__DIR__,2)).'/tools/demo-review/download-probe.php';

// Leave less free PHP memory than the response size, like the failing demo.
$pressure=str_repeat('x',80*1024*1024);
$file=tmpfile();
if(!$file||!ftruncate($file,64*1024*1024)||fwrite($file,'PK')!==2)throw new RuntimeException('Cannot prepare synthetic stream.');
$expected=hash_file('sha256',stream_get_meta_data($file)['uri']);rewind($file);
$response=new \Symfony\Component\HttpFoundation\StreamedResponse(static function()use($file){fpassthru($file);});
ob_start();$level=ob_get_level();
$report=\ManualDemo\probeDownloadStream($response,'PK');
if($report['bytes']!==64*1024*1024||$report['sha256']!==$expected||ob_get_level()!==$level||ob_get_clean()!=='')throw new RuntimeException('Stream verification failed.');
fclose($file);
foreach(['invalid','exception'] as $case){
    $level=ob_get_level();
    $response=new \Symfony\Component\HttpFoundation\StreamedResponse(static function()use($case){echo 'NOPE';if($case==='exception')throw new RuntimeException('expected');});
    try{\ManualDemo\probeDownloadStream($response,'%PDF');throw new LogicException('Invalid stream was accepted.');}
    catch(RuntimeException $error){if(ob_get_level()!==$level)throw new LogicException('Output buffer leaked.');}
}
if(strlen($pressure)!==80*1024*1024)throw new RuntimeException('Memory pressure was lost.');
echo json_encode(['stream_bytes'=>$report['bytes'],'peak_bytes'=>memory_get_peak_usage(true),'memory_limit'=>ini_get('memory_limit'),'invalid_rejected'=>true,'exception_cleaned'=>true],JSON_THROW_ON_ERROR)."\n";
