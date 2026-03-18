<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_record_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_record_id')->constrained('support_records')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->integer('duration_minutes')->nullable(); // 実施時間（分）
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_record_programs');
    }
};
