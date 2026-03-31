<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff_work_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->enum('day_of_week', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);
            $table->time('start_time')->nullable();
            $table->string('work_type', 30)->default('');
            $table->timestamps();

            $table->unique(['staff_id', 'day_of_week']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_work_patterns');
    }
};
