<?php

namespace ManualDemo;

/** Consume a real download without retaining its body or writing it to stdout. */
function probeDownloadStream(object $response, string $prefix): array
{
    $bytes=0;$head='';$digest=hash_init('sha256');$level=ob_get_level();
    ob_start(static function(string $chunk)use(&$bytes,&$head,$digest):string{
        $bytes+=strlen($chunk);
        if(strlen($head)<4)$head.=substr($chunk,0,4-strlen($head));
        hash_update($digest,$chunk);
        return '';
    },8192);
    try{
        $response->sendContent();
        if(ob_get_level()!==$level+1)throw new \RuntimeException('Download changed its output buffer.');
        ob_end_flush();
    }finally{
        while(ob_get_level()>$level)ob_end_clean();
    }
    if($bytes<4||!str_starts_with($head,$prefix))throw new \RuntimeException('PDF・ZIPの形式を確認できません。');
    return ['bytes'=>$bytes,'sha256'=>hash_final($digest)];
}
