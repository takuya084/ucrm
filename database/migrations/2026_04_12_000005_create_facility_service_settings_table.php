<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facility_service_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_code_master_id')->constrained('service_code_masters')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'service_code_master_id'], 'facility_service_code_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('facility_service_settings');
    }
};
