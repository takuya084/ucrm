<?php
namespace App\Privacy;
final class LogProvider
{
    public static function boot(): void {
        $channels=config('logging.channels',[]);
        foreach($channels as $name=>$settings){
            if($name==='emergency'||($settings['driver']??'')==='null')continue;
            $channels[$name]=['driver'=>'single','path'=>storage_path('logs/private.log'),'level'=>'debug','tap'=>[ProtectLogs::class]];
        }
        $channels['private']=['driver'=>'single','path'=>storage_path('logs/private.log'),'level'=>'debug','tap'=>[ProtectLogs::class]];
        config(['logging.channels'=>$channels,'logging.default'=>'private']);
    }
}
