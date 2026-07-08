<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('child_id')->constrained();
            // 支援記録と1対1（家庭側記入が先行した場合は null のまま作られ、後から紐付く）
            $table->foreignId('support_record_id')->nullable()->constrained();
            $table->foreignId('staff_id')->nullable()->constrained('staff');
            $table->date('date');

            // ── 施設側記入（保護者に公開される内容のみ。内部の見立ては support_records 側に書く）
            $table->string('meal_note', 255)->nullable();          // おやつ・食事
            $table->string('health_note', 255)->nullable();        // 体調（客観的事実）
            $table->text('guardian_message')->nullable();          // 保護者向けメッセージ
            $table->json('five_domain_tags')->nullable();          // 5領域タグ（モニタリング集計用）
            $table->string('goal_progress', 20)->nullable();       // 短期目標への手応え achieved/partial/difficult

            // ── 公開管理
            $table->string('status', 20)->default('draft');        // draft / published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('staff');
            $table->timestamp('yoyaku_synced_at')->nullable();     // p-yoyaku への配信済み日時
            $table->timestamp('read_at')->nullable();              // 保護者既読（p-yoyaku から受信）

            // ── 家庭側記入（p-yoyaku から受信）
            $table->string('home_temperature', 10)->nullable();
            $table->string('home_sleep', 100)->nullable();
            $table->string('home_medication', 100)->nullable();
            $table->string('home_condition', 100)->nullable();
            $table->text('guardian_comment')->nullable();
            $table->timestamp('guardian_submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['child_id', 'date']);
            $table->index(['facility_id', 'date']);
            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_notes');
    }
};
