<?php
namespace App\Privacy;
use Illuminate\Support\Facades\Storage;
final class StorageProvider
{
    public static function boot(): void {
        Storage::extend('private-local',function($app,$config){
            $adapter=new \League\Flysystem\Local\LocalFilesystemAdapter($config['root'],null,LOCK_EX,\League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS);
            $driver=new \League\Flysystem\Filesystem($adapter,$config);
            return new PrivateFilesystem($driver,$adapter,$config);
        });
    }
}
