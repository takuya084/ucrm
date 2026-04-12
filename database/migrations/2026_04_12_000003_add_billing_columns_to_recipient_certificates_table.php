<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('recipient_certificates', function (Blueprint $table) {
            $table->integer('copayment_rate')->default(10)->after('monthly_limit')->comment('自己負担割合%');
            $table->integer('copayment_cap_monthly')->nullable()->after('copayment_rate')->comment('上限月額(円)');
            $table->boolean('is_cap_management_target')->default(false)->after('copayment_cap_monthly')->comment('上限管理対象');
            $table->foreignId('cap_managing_facility_id')->nullable()->after('is_cap_management_target')
                ->constrained('facilities')->nullOnDelete()->comment('上限管理事業所');
            $table->string('service_type')->nullable()->after('cap_managing_facility_id')->comment('サービス種別: houday, jidou');
            $table->string('municipality_code', 6)->nullable()->after('service_type')->comment('市区町村コード');
        });
    }

    public function down()
    {
        Schema::table('recipient_certificates', function (Blueprint $table) {
            $table->dropForeign(['cap_managing_facility_id']);
            $table->dropColumn([
                'copayment_rate',
                'copayment_cap_monthly',
                'is_cap_management_target',
                'cap_managing_facility_id',
                'service_type',
                'municipality_code',
            ]);
        });
    }
};
