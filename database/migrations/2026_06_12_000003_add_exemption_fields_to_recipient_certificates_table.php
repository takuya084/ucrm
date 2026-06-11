<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipient_certificates', function (Blueprint $table) {
            // 満3歳到達後最初の4/1から就学前までの児童発達支援は利用者負担無償
            $table->boolean('is_free_of_charge')->default(false)->after('copayment_cap_monthly')
                ->comment('就学前児童発達支援の無償化対象');
            // 多子軽減・自治体独自助成等
            $table->string('reduction_type', 50)->nullable()->after('is_free_of_charge')
                ->comment('軽減区分: multi_child(多子軽減), municipal(自治体助成) 等');
            // 国保連明細書に記載する通所給付決定保護者氏名
            $table->string('guardian_name', 100)->nullable()->after('reduction_type')
                ->comment('通所給付決定保護者氏名');
        });
    }

    public function down(): void
    {
        Schema::table('recipient_certificates', function (Blueprint $table) {
            $table->dropColumn(['is_free_of_charge', 'reduction_type', 'guardian_name']);
        });
    }
};
