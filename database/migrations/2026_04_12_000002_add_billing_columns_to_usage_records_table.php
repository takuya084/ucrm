<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->time('check_in_time')->nullable()->after('status')->comment('来所時間');
            $table->time('check_out_time')->nullable()->after('check_in_time')->comment('退所時間');
            $table->string('service_type')->nullable()->after('check_out_time')->comment('サービス種別: houday, jidou');
            $table->boolean('is_school_day')->default(true)->after('service_type')->comment('学校日フラグ');
        });
    }

    public function down()
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropColumn(['check_in_time', 'check_out_time', 'service_type', 'is_school_day']);
        });
    }
};
