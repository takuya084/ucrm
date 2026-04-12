<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('billing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type')->comment('houday or jidou');
            $table->integer('total_days')->default(0)->comment('利用日数');
            $table->integer('total_units')->default(0)->comment('合計単位数');
            $table->decimal('unit_price_yen', 10, 2)->default(10.00)->comment('単位数単価');
            $table->integer('total_amount')->default(0)->comment('費用合計');
            $table->integer('insurance_amount')->default(0)->comment('給付費');
            $table->integer('copayment_amount')->default(0)->comment('利用者負担額');
            $table->integer('copayment_cap')->default(0)->comment('上限月額');
            $table->integer('copayment_cap_applied')->default(0)->comment('上限適用後負担額');
            $table->string('status')->default('draft')->comment('draft, confirmed, submitted, returned, corrected');
            $table->timestamps();

            $table->unique(['billing_period_id', 'child_id', 'service_type'], 'billing_detail_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('billing_details');
    }
};
