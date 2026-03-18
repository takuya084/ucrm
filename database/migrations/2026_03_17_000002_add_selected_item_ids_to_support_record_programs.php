<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_record_programs', function (Blueprint $table) {
            $table->json('selected_item_ids')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('support_record_programs', function (Blueprint $table) {
            $table->dropColumn('selected_item_ids');
        });
    }
};
