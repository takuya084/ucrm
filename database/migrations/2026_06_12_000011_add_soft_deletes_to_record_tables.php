<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 支援記録・モニタリング記録・個別支援計画は指定基準の保存義務対象のため、
     * 画面からの削除はソフトデリートとし証跡を残す（改ざん防止・実地指導対応）。
     */
    public function up(): void
    {
        Schema::table('support_records', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('support_plans', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('support_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('support_plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
