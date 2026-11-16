<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_transport_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('service_date');
            $table->string('direction', 10);
            $table->time('scheduled_time')->nullable();
            $table->string('location', 120)->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'service_date', 'child_id', 'direction'], 'daily_transport_child_direction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_transport_assignments');
    }
};
