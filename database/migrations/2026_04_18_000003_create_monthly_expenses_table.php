<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->char('year_month', 7); // Y-m
            $table->enum('category', [
                'rent', 'utilities', 'communications', 'supplies',
                'vehicle', 'training', 'welfare', 'others',
            ]);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'year_month', 'category']);
            $table->index(['facility_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_expenses');
    }
};
