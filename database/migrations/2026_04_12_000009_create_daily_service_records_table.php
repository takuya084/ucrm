<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_code_master_id')->constrained('service_code_masters');
            $table->string('service_code', 6);
            $table->integer('units')->default(0);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_pickup')->default(false)->comment('送迎（迎え）');
            $table->boolean('is_dropoff')->default(false)->comment('送迎（送り）');
            $table->boolean('is_extension')->default(false)->comment('延長支援');
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['usage_record_id', 'service_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_service_records');
    }
};
