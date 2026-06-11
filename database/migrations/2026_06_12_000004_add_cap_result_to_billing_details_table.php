<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_details', function (Blueprint $table) {
            // 国保連明細書に記載する上限額管理結果（管理事業所番号・結果コード・結果額）
            $table->char('cap_management_result_code', 1)->nullable()->after('copayment_cap_applied')
                ->comment('上限管理結果: 1=管理なし充当, 2=複数事業所・上限内, 3=上限超過・調整');
            $table->string('cap_managing_facility_code', 10)->nullable()->after('cap_management_result_code')
                ->comment('上限額管理事業所番号');
            $table->integer('cap_result_amount')->nullable()->after('cap_managing_facility_code')
                ->comment('上限管理後利用者負担額（管理結果額）');
        });
    }

    public function down(): void
    {
        Schema::table('billing_details', function (Blueprint $table) {
            $table->dropColumn(['cap_management_result_code', 'cap_managing_facility_code', 'cap_result_amount']);
        });
    }
};
