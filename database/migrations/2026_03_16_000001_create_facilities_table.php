<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // 事業所名
            $table->string('address')->nullable();            // 住所
            $table->string('tel')->nullable();                // 電話番号
            $table->integer('capacity_per_day')->default(10); // 1日の定員
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
