<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete(); // 対応者
            $table->enum('contact_method', ['tel', 'line', 'email', 'in_person', 'other'])->default('tel');
            $table->dateTime('contacted_at');                // 問い合わせ日時
            $table->enum('category', ['schedule', 'support', 'billing', 'complaint', 'other'])->default('other');
            // schedule: 日程, support: 支援内容, billing: 請求, complaint: 苦情
            $table->string('subject')->nullable();           // 件名
            $table->text('content');                         // 問い合わせ内容
            $table->text('response')->nullable();            // 回答・対応内容
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->boolean('is_escalated')->default(false); // 管理者エスカレーション済み
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
