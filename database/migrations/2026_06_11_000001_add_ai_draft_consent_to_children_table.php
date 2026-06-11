<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            // 個人情報保護法28条（外国にある第三者への提供）対応:
            // AI下書き生成（外部API送信）には保護者の同意記録を必須とする
            $table->timestamp('ai_draft_consented_at')->nullable()->after('memo')
                ->comment('AI下書き生成への保護者同意日時（null=未同意）');
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('ai_draft_consented_at');
        });
    }
};
