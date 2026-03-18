<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('birthdate')->nullable();
            $table->string('grade')->nullable();             // 学年 例: 小1, 小2
            $table->string('disability_type')->nullable();   // 障がい種別
            $table->text('disability_note')->nullable();     // 障がい備考
            $table->text('allergy_note')->nullable();        // アレルギー
            $table->text('care_note')->nullable();           // 配慮事項
            $table->boolean('pickup_required')->default(false); // 送迎有無
            $table->string('pickup_address')->nullable();    // 送迎先住所
            $table->string('pickup_area')->nullable();       // 送迎エリア
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('contract_status', ['active', 'suspended', 'ended'])->default('active');
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
