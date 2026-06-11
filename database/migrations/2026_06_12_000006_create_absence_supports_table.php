<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_supports', function (Blueprint $table) {
            $table->id();
            // 欠席時対応加算の算定要件: 欠席連絡への相談援助の記録
            $table->foreignId('usage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete()->comment('対応職員');
            $table->dateTime('contacted_at')->comment('連絡受付日時');
            $table->string('contact_method', 20)->default('tel')->comment('連絡方法: tel, app, in_person 等');
            $table->text('support_content')->comment('相談援助の内容（次回利用の調整・家庭での様子確認等）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_supports');
    }
};
