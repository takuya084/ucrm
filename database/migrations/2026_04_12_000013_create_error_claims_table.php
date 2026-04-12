<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('error_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_detail_id')->constrained();
            $table->string('original_year_month', 7);
            $table->foreignId('child_id')->constrained();
            $table->string('claim_type')->comment('full_cancel, partial_correction');
            $table->text('reason');
            $table->string('status')->default('draft')->comment('draft, submitted, accepted, rejected');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('error_claims');
    }
};
