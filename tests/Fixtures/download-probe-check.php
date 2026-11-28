<?php

require ($argv[1]??dirname(__DIR__,2)).'/tools/demo-review/download-probe.php';

// More data than the remaining PHP memory, including on official Docker images.
$pressure=str_repeat('x',80*1024*1024);
$file=tmpfile();
if(!$file||!ftruncate($file,64*1024*1024)||fwrite($file,'PK')!==2)throw new RuntimeException('Cannot prepare synthetic file.');
$path=stream_get_meta_data($file)['uri'];$expected=hash_file('sha256',$path);
ob_start();$level=ob_get_level();
$report=\ManualDemo\probeDownloadFile($path,'PK');
if($report['bytes']!==64*1024*1024||$report['sha256']!==$expected||ob_get_level()!==$level||ob_get_clean()!=='')throw new RuntimeException('File verification failed.');
try{\ManualDemo\probeDownloadFile($path,'%PDF');throw new LogicException('Invalid signature was accepted.');}
catch(RuntimeException $error){}
fclose($file);
try{\ManualDemo\probeDownloadFile($path,'PK');throw new LogicException('Missing file was accepted.');}
catch(RuntimeException $error){}
if(strlen($pressure)!==80*1024*1024)throw new RuntimeException('Memory pressure was lost.');
echo json_encode(['file_bytes'=>$report['bytes'],'peak_bytes'=>memory_get_peak_usage(true),'memory_limit'=>ini_get('memory_limit'),'invalid_rejected'=>true,'missing_rejected'=>true],JSON_THROW_ON_ERROR)."\n";
