<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->unsignedBigInteger('yoyaku_business_id')->nullable()->after('capacity_per_day')
                ->comment('p-yoyaku の businesses.id（未設定=連携なし、固定曜日にフォールバック）');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('yoyaku_business_id');
        });
    }
};
