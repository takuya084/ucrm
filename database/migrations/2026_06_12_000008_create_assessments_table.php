<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            // 個別支援計画作成プロセスの起点となるアセスメント記録（指定基準）
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete()->comment('実施者（児発管）');
            $table->date('assessed_at')->comment('実施日');
            $table->text('physical_condition')->nullable()->comment('心身の状況');
            $table->text('living_environment')->nullable()->comment('生活環境・家庭状況');
            $table->text('child_intention')->nullable()->comment('本人の意向');
            $table->text('guardian_intention')->nullable()->comment('保護者の意向');
            $table->json('five_domains')->nullable()->comment('5領域別の発達状況(JSON)');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
