<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedBigInteger('previous_plan_id')->nullable(); // 前回計画（自己参照）
            $table->date('plan_date');                       // 計画作成日
            $table->date('valid_from')->nullable();          // 計画有効期間（開始）
            $table->date('valid_to')->nullable();            // 計画有効期間（終了）
            $table->text('long_term_goal')->nullable();      // 長期目標
            $table->text('short_term_goal')->nullable();     // 短期目標
            $table->text('support_policy')->nullable();      // 支援方針
            $table->text('program_content')->nullable();     // 支援内容
            $table->boolean('guardian_agreement')->default(false);       // 保護者同意
            $table->date('guardian_agreement_date')->nullable();          // 同意取得日
            $table->string('document_path')->nullable();     // ファイルパス
            $table->timestamps();

            $table->foreign('previous_plan_id')->references('id')->on('support_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_plans');
    }
};
