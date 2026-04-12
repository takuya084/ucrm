<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('copayment_cap_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copayment_cap_management_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained();
            $table->string('facility_name');
            $table->integer('total_amount')->default(0)->comment('総費用額');
            $table->integer('copayment_amount')->default(0)->comment('利用者負担額');
            $table->integer('adjusted_amount')->default(0)->comment('調整後負担額');
            $table->boolean('is_managing_facility')->default(false)->comment('上限管理事業所かどうか');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('copayment_cap_details');
    }
};
