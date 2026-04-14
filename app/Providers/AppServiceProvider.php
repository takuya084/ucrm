<?php

namespace App\Providers;

use App\Models\UsageRecord;
use App\Observers\UsageRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        UsageRecord::observe(UsageRecordObserver::class);
    }
}
