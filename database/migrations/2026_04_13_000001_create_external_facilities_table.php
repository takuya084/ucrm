<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 20)->comment('放デイ/児発/保育所等訪問 等');
            $table->string('facility_number', 10)->comment('事業所番号（10桁）');
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('satellite_type', 20)->default('main')->comment('main:本体 / satellite:サテライト');
            $table->string('phone', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'facility_number'], 'ext_facility_unique');
            $table->index('facility_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_facilities');
    }
};
