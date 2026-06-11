<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 個別支援計画の担当者会議（指定基準: 計画作成にあたる会議の記録）
        Schema::create('support_plan_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_plan_id')->constrained()->cascadeOnDelete();
            $table->dateTime('held_at')->comment('開催日時');
            $table->json('attendees')->nullable()->comment('出席者(JSON: 氏名・職種)');
            $table->text('minutes')->nullable()->comment('議事概要・専門的見地からの意見');
            $table->timestamps();
        });

        // 保護者同意・交付の記録（電子署名対応）
        Schema::create('support_plan_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('consented_at')->comment('同意日時');
            $table->string('method', 20)->default('paper')->comment('paper(押印/署名), electronic(電子同意)');
            $table->longText('signature_data')->nullable()->comment('電子署名画像(base64)等');
            $table->string('signed_ip', 45)->nullable()->comment('電子同意時のIP');
            $table->string('document_hash', 64)->nullable()->comment('同意対象文書のSHA-256（改ざん検知）');
            $table->dateTime('delivered_at')->nullable()->comment('計画書の交付日時');
            $table->timestamps();
        });

        // 個別支援計画の承認フロー（原案→児発管承認→交付）
        Schema::table('support_plans', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('document_path')
                ->comment('draft(原案), approved(児発管承認済), delivered(交付済)');
            $table->foreignId('approved_by')->nullable()->after('status')
                ->constrained('staff')->nullOnDelete()->comment('承認した児発管');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('support_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });
        Schema::dropIfExists('support_plan_consents');
        Schema::dropIfExists('support_plan_meetings');
    }
};
