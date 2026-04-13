<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_external_facility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_certificate_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('external_facility_id')
                ->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['recipient_certificate_id', 'external_facility_id'],
                'cert_ext_facility_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_external_facility');
    }
};
