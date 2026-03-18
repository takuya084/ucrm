<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('date');
            $table->enum('status', ['attended', 'absent', 'absent_notice', 'cancel'])->default('attended');
            // attended: 出席, absent: 無断欠席, absent_notice: 欠席連絡あり, cancel: キャンセル
            $table->string('absent_reason')->nullable();     // 欠席理由
            $table->boolean('pickup_done')->default(false);  // 迎え実施
            $table->boolean('dropoff_done')->default(false); // 送り実施
            $table->boolean('billing_target')->default(true);// 請求対象かどうか
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'date']);            // 同じ児童の同日重複防止
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
