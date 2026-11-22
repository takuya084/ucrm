<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->uuid('delivery_request_id')->nullable();
            $table->dateTime('delivery_requested_at')->nullable();
            $table->dateTime('delivery_attempted_at')->nullable();
            $table->string('delivery_error_code', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_notes', fn (Blueprint $table) => $table->dropColumn([
            'delivery_request_id', 'delivery_requested_at', 'delivery_attempted_at', 'delivery_error_code',
        ]));
    }
};
