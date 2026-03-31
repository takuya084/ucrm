<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shift_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_shift_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->string('work_type', 30)->default('');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['monthly_shift_id', 'staff_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shift_entries');
    }
};
