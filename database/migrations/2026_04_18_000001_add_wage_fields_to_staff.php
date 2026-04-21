<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('employment_type', ['full_time', 'part_time', 'contract'])
                ->default('full_time')
                ->after('role');
            $table->decimal('monthly_salary', 10, 2)->nullable()->after('employment_type');
            $table->decimal('hourly_wage',     8, 2)->nullable()->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['employment_type', 'monthly_salary', 'hourly_wage']);
        });
    }
};
