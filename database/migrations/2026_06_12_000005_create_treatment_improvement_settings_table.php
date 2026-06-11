<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_improvement_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            // 加算率計算のベースになるサービスコード（福祉・介護職員等処遇改善加算等）
            $table->foreignId('service_code_master_id')->constrained('service_code_masters');
            $table->decimal('rate', 5, 2)->comment('加算率(%)。基本報酬+加算の合計単位数に乗じる');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_improvement_settings');
    }
};
