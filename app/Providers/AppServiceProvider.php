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
        if ($this->app->runningInConsole()) $this->commands([\App\Privacy\MaintenanceCommand::class,\App\Privacy\KeyCommand::class]);

        UsageRecord::observe(UsageRecordObserver::class);
    }
}
