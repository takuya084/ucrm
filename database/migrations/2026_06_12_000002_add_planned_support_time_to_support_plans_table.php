<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_plans', function (Blueprint $table) {
            // 令和6年度報酬改定: 基本報酬は個別支援計画に定めた支援時間の時間区分で算定する
            $table->time('planned_start_time')->nullable()->after('program_content')->comment('計画上の支援開始時刻');
            $table->time('planned_end_time')->nullable()->after('planned_start_time')->comment('計画上の支援終了時刻');
            $table->integer('planned_duration_minutes')->nullable()->after('planned_end_time')->comment('計画上の支援時間（分）');
            // R6基準: 5領域（健康・生活/運動・感覚/認知・行動/言語・コミュニケーション/人間関係・社会性）との関連
            $table->json('five_domains')->nullable()->after('planned_duration_minutes')->comment('5領域との関連(JSON)');
        });
    }

    public function down(): void
    {
        Schema::table('support_plans', function (Blueprint $table) {
            $table->dropColumn(['planned_start_time', 'planned_end_time', 'planned_duration_minutes', 'five_domains']);
        });
    }
};
