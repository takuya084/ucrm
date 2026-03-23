<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('facility_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('facility_id');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign(['facility_id']);
            $table->dropColumn('facility_id');
        });
    }
};
