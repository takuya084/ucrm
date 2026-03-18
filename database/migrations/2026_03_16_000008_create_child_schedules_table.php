<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->enum('day_of_week', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
            $table->date('start_date');                      // この曜日登録の有効開始日
            $table->date('end_date')->nullable();            // この曜日登録の有効終了日
            $table->enum('status', ['regular', 'temporary', 'trial'])->default('regular');
            // regular: 定期, temporary: 一時, trial: 体験
            $table->boolean('pickup_required')->default(false);
            $table->string('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_schedules');
    }
};
