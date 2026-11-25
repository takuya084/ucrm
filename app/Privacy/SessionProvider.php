<?php
namespace App\Privacy;
use Illuminate\Support\Facades\Session;
final class SessionProvider
{
    public static function boot(): void {
        Session::extend('private-file',fn($app)=>new PrivateSessionHandler($app['files'],config('session.files'),config('session.lifetime')));
        if(config('session.driver')==='file')config(['session.driver'=>'private-file']);
    }
}
