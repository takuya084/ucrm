<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->string('qualification', 30);
            $table->timestamps();
            $table->unique(['staff_id', 'qualification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_qualifications');
    }
};
