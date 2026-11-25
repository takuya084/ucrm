<?php
namespace App\Privacy;
use Illuminate\Support\Facades\Cache;
final class CacheProvider
{
    public static function boot(): void {
        $stores=config('cache.stores',[]);
        foreach($stores as $name=>$settings){
            if(in_array($settings['driver']??'', ['array','null','private-cache'],true)||str_starts_with($name,'_privacy_backend_'))continue;
            $backend='_privacy_backend_'.$name;config(['cache.stores.'.$backend=>$settings,'cache.stores.'.$name=>['driver'=>'private-cache','backend'=>$backend]]);
        }
        Cache::extend('private-cache',fn($app,$settings)=>Cache::repository(new CacheStore($app['cache']->store($settings['backend'])->getStore())));
    }
}
