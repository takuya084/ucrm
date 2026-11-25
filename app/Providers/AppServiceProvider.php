<?php

namespace App\Providers;

use App\Models\UsageRecord;
use App\Observers\UsageRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        \Illuminate\Database\Connection::resolverFor('sqlite', fn($pdo,$database,$prefix,$config) => new \App\Privacy\SqliteConnection($pdo,$database,$prefix,$config));
        \Illuminate\Database\Connection::resolverFor('mysql', fn($pdo,$database,$prefix,$config) => new \App\Privacy\MysqlConnection($pdo,$database,$prefix,$config));

        //
    }

    public function boot()
    {
        \App\Privacy\SessionProvider::boot();
        \App\Privacy\LogProvider::boot();
        \App\Privacy\CacheProvider::boot();
        \App\Privacy\State::$legacyMigration=false;
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\MigrationStarted::class,function($event){
            $name=basename((new \ReflectionClass($event->migration))->getFileName());
            \App\Privacy\State::$legacyMigration=$event->method==='up' && $name<'2026_11_25_120000_encrypt_personal_data.php';
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\MigrationEnded::class,fn()=>\App\Privacy\State::$legacyMigration=false);

        \App\Privacy\StorageProvider::boot();
        if ($this->app->runningInConsole()) $this->commands([\App\Privacy\MaintenanceCommand::class,\App\Privacy\KeyCommand::class,\App\Privacy\FileCommand::class,\App\Privacy\BackupCommand::class,\App\Privacy\RestoreCommand::class,\App\Privacy\LogsCommand::class,\App\Privacy\CacheCommand::class,\App\Privacy\SessionsCommand::class]);

        UsageRecord::observe(UsageRecordObserver::class);
    }
}
