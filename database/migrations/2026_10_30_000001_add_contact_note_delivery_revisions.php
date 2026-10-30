<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_revision')->default(0);
            $table->unsignedBigInteger('guardian_source_revision')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('contact_notes', fn(Blueprint $table) => $table->dropColumn(['delivery_revision','guardian_source_revision']));
    }
};
