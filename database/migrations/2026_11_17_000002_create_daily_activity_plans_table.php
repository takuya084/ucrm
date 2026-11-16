<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_activity_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('activity_date');
            $table->string('category', 20);
            $table->string('title');
            $table->time('starts_at')->nullable();
            $table->text('materials')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activity_plans');
    }
};
