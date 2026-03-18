<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('usage_record_id')->nullable()->constrained('usage_records')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('date');
            $table->enum('condition', ['good', 'normal', 'poor'])->default('normal'); // 児童の様子
            $table->text('behavior_note')->nullable();        // 行動・様子メモ
            $table->text('achievement_note')->nullable();     // 成功体験・できたこと
            $table->text('challenge_note')->nullable();       // 課題・気になること
            $table->text('care_note')->nullable();            // 当日の配慮事項
            $table->text('next_action')->nullable();          // 次回への申し送り
            $table->boolean('is_shared_with_guardian')->default(false); // 保護者共有（連絡帳）
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_records');
    }
};
