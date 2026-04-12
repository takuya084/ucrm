<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('guardian_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained();
            $table->foreignId('child_id')->constrained();
            $table->foreignId('facility_id')->constrained();
            $table->string('year_month', 7);
            $table->integer('copayment_amount')->default(0)->comment('自己負担額');
            $table->integer('other_charges')->default(0)->comment('その他費用(おやつ代等)');
            $table->integer('total_amount')->default(0)->comment('請求合計');
            $table->string('payment_status')->default('unpaid')->comment('unpaid, paid, partial, overdue');
            $table->string('payment_method')->nullable()->comment('bank_transfer, cash, other');
            $table->dateTime('paid_at')->nullable();
            $table->integer('paid_amount')->nullable();
            $table->date('due_date')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('guardian_invoices');
    }
};
