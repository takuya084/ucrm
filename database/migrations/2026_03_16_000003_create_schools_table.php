<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('address')->nullable();
            $table->enum('school_type', ['elementary', 'junior_high', 'special_needs', 'other'])->default('elementary');
            $table->time('end_time_regular')->nullable();    // 通常下校時刻
            $table->time('end_time_early')->nullable();      // 早退・短縮時下校時刻
            $table->string('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
