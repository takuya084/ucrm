<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_categories', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique(); // 01〜19=級地, 20=その他（国保連インタフェース仕様書 共通編1.4 に準拠）
            $table->string('label');           // 例: 一級地, その他
            $table->decimal('unit_price', 10, 2)->nullable(); // 1単位あたりの金額（円）。告示で確認済みの値のみ設定。未確認はnull
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_categories');
    }
};
