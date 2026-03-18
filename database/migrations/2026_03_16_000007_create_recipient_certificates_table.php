<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->string('certificate_number')->nullable(); // 受給者証番号
            $table->string('municipality')->nullable();       // 市区町村
            $table->date('valid_from')->nullable();           // 有効期間開始
            $table->date('valid_to')->nullable();             // 有効期間終了
            $table->integer('monthly_limit')->default(23);   // 月あたり支給量（回数）
            $table->string('disability_support_category')->nullable(); // 通所支援種別
            $table->date('issue_date')->nullable();           // 交付日
            $table->enum('status', ['active', 'expired', 'pending'])->default('active');
            $table->string('document_path')->nullable();      // スキャン画像パス
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_certificates');
    }
};
