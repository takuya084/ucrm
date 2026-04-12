<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('claim_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->string('year_month', 7);
            $table->foreignId('child_id')->constrained();
            $table->string('return_code')->nullable();
            $table->text('return_reason')->nullable();
            $table->integer('original_amount')->default(0);
            $table->string('status')->default('returned')->comment('returned, resubmitting, resubmitted, resolved');
            $table->foreignId('resubmitted_billing_detail_id')->nullable()->constrained('billing_details')->nullOnDelete();
            $table->date('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('claim_returns');
    }
};
