<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            // 国保連明細書 基本情報レコードの必須項目（インタフェース仕様書 共通編1.4: 01=一級地〜20=その他）
            $table->char('area_category_code', 2)->nullable()->after('area_unit_price')->comment('地域区分コード');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('area_category_code');
        });
    }
};
