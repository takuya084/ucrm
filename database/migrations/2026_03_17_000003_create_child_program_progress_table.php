<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_program_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('program_item_id')->constrained('program_items')->onDelete('cascade');
            $table->enum('status', ['practicing', 'mastered']); // 練習中 / 達成
            $table->date('achieved_at')->nullable();             // 達成日
            $table->timestamps();
            $table->unique(['child_id', 'program_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_program_progress');
    }
};
