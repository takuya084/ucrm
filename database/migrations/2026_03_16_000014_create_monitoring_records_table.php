<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('monitoring_date');                 // モニタリング実施日
            $table->date('period_from')->nullable();         // 対象期間（開始）
            $table->date('period_to')->nullable();           // 対象期間（終了）
            $table->text('support_summary')->nullable();     // 支援の経過まとめ
            $table->text('strengths')->nullable();           // 強み・できるようになったこと
            $table->text('challenges')->nullable();          // 課題
            $table->text('guardian_needs')->nullable();      // 保護者ニーズ
            $table->text('environmental_notes')->nullable(); // 環境・家庭状況
            $table->date('next_review_date')->nullable();    // 次回モニタリング予定日
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_records');
    }
};
