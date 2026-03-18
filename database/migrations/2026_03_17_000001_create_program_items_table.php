<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->string('name');                              // 例: ストレートジャンプ
            $table->unsignedSmallInteger('difficulty_order')->default(0); // 難易度順
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_items');
    }
};
