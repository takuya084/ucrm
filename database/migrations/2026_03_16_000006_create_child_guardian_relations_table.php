<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_guardian_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('guardian_id')->constrained('guardians')->onDelete('cascade');
            $table->boolean('is_primary')->default(false);            // 主たる連絡先
            $table->boolean('is_emergency_contact')->default(false);  // 緊急連絡先
            $table->integer('priority_order')->default(1);            // 連絡優先順位
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_guardian_relations');
    }
};
