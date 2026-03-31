<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shift_day_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_shift_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('note', 500);
            $table->timestamps();

            $table->unique(['monthly_shift_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shift_day_notes');
    }
};
