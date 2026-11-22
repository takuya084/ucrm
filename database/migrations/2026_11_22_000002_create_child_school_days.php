<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('school_calendar_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('month',7);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['facility_id','month']);
        });
        Schema::create('child_school_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('child_id')->constrained();
            $table->date('date');
            $table->boolean('is_school_day');
            $table->foreignId('updated_by')->nullable()->constrained('staff');
            $table->timestamps();
            $table->unique(['facility_id','child_id','date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_school_days');
        Schema::dropIfExists('school_calendar_months');
    }
};
