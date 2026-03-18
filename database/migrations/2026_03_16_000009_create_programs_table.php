<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->string('name');                          // 例: トランポリン, SST, 宿題支援
            $table->enum('category', ['physical', 'cognitive', 'social', 'life_skills', 'other'])->default('other');
            // physical: 運動, cognitive: 認知, social: 社会性, life_skills: 生活スキル
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
