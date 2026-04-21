<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_labels', function (Blueprint $table) {
            $table->decimal('work_hours', 4, 2)->nullable()->after('is_off');
        });
    }

    public function down(): void
    {
        Schema::table('shift_labels', function (Blueprint $table) {
            $table->dropColumn('work_hours');
        });
    }
};
