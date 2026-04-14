<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('yoyaku_api_token', 255)->nullable()->after('yoyaku_business_id');
            $table->string('yoyaku_webhook_secret', 64)->nullable()->after('yoyaku_api_token');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['yoyaku_api_token', 'yoyaku_webhook_secret']);
        });
    }
};
