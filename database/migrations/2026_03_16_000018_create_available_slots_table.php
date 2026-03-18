<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('available_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('day_of_week', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
            $table->integer('total_capacity');               // 定員
            $table->integer('booked_count')->default(0);    // 予約済み数
            // available_count は total_capacity - booked_count で算出
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'date']);         // 同施設・同日重複防止
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('available_slots');
    }
};
