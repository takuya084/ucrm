<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('billing_detail_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_code_master_id')->constrained('service_code_masters');
            $table->string('service_code', 6);
            $table->string('service_name');
            $table->integer('count')->default(0)->comment('回数');
            $table->integer('units_per_count')->default(0)->comment('1回あたり単位数');
            $table->integer('total_units')->default(0)->comment('合計単位数');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('billing_detail_lines');
    }
};
