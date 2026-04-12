<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('facility_code', 10)->nullable()->after('name')->comment('事業所番号');
            $table->string('service_type')->default('houday')->after('facility_code')->comment('サービス種別: houday, jidou, both');
            $table->decimal('area_unit_price', 10, 2)->default(10.00)->after('service_type')->comment('地域区分単価');
            $table->date('designated_date')->nullable()->after('area_unit_price')->comment('指定日');
            $table->string('administrator_name')->nullable()->after('designated_date')->comment('管理者氏名');
            $table->string('fax')->nullable()->after('tel');
        });
    }

    public function down()
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn([
                'facility_code',
                'service_type',
                'area_unit_price',
                'designated_date',
                'administrator_name',
                'fax',
            ]);
        });
    }
};
