<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete(); // 作成者
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['closure', 'event', 'emergency', 'individual', 'general'])->default('general');
            // closure: 休所, event: 行事, emergency: 緊急, individual: 個別, general: 一般
            $table->enum('channel', ['email', 'line', 'both'])->default('email');
            $table->dateTime('send_at')->nullable();          // 送信日時（予約対応）
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed'])->default('draft');
            $table->json('target_condition')->nullable();     // 配信条件スナップショット
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
