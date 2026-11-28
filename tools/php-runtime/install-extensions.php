<?php
$allowed=['bcmath','gd','zip','intl','mbstring','curl','pdo_sqlite','pdo_mysql','mysqli','pcntl','exif','sockets','opcache'];
$required=json_decode(file_get_contents('/tmp/runtime-extensions.json'),true,512,JSON_THROW_ON_ERROR);
$missing=[];
foreach($required as $name){
    if(!is_string($name)||!preg_match('/^[A-Za-z0-9_ ]{1,40}$/D',$name))throw new RuntimeException('Invalid extension name.');
    if(extension_loaded($name))continue;
    $ext=$name==='Zend OPcache'?'opcache':strtolower($name);
    if(!in_array($ext,$allowed,true)||!is_dir('/usr/src/php/ext/'.$ext))throw new RuntimeException('Unsupported existing extension: '.$name);
    $missing[$ext]=true;
}
echo implode("\n",array_keys($missing)).($missing?"\n":'');
