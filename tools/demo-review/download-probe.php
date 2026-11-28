<?php

namespace ManualDemo;

/** Inspect a generated file using bounded reads; never capture a response body. */
function probeDownloadFile(string $path, string $prefix): array
{
    clearstatcache(true,$path);
    if(!is_file($path)||is_link($path))throw new \RuntimeException('PDF・ZIPの保存ファイルを確認できません。');
    $file=fopen($path,'rb');
    if($file===false)throw new \RuntimeException('PDF・ZIPの保存ファイルを開けません。');
    $bytes=0;$head='';$digest=hash_init('sha256');
    try{
        $expected=fstat($file)['size'];
        while(!feof($file)){
            $chunk=fread($file,8192);
            if($chunk===false||($chunk===''&&!feof($file)))throw new \RuntimeException('PDF・ZIPを読み取れません。');
            $bytes+=strlen($chunk);
            if(strlen($head)<4)$head.=substr($chunk,0,4-strlen($head));
            hash_update($digest,$chunk);
        }
    }finally{fclose($file);}
    if($bytes!==$expected||$bytes<4||!str_starts_with($head,$prefix))throw new \RuntimeException('PDF・ZIPの形式とサイズを確認できません。');
    return ['bytes'=>$bytes,'sha256'=>hash_final($digest)];
}
