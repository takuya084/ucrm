<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            // 国保連明細書の契約情報欄（契約支給量・契約日・事業者記入欄番号）
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('contracted_amount')->comment('契約支給量（日/月）');
            $table->date('contract_start_date')->comment('契約開始日');
            $table->date('contract_end_date')->nullable()->comment('契約終了日');
            $table->string('record_number', 2)->nullable()->comment('事業者記入欄番号');
            $table->timestamps();

            $table->index(['child_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
