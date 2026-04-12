<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_code_masters', function (Blueprint $table) {
            $table->id();
            $table->date('revision_date')->comment('報酬改定日');
            $table->string('service_type')->comment('サービス種別: houday(63), jidou(61)');
            $table->string('service_code', 6)->comment('サービスコード');
            $table->string('service_name')->comment('サービス内容名');
            $table->integer('unit_count')->comment('単位数');
            $table->string('unit_type')->default('per_day')->comment('単位種別: per_day, per_month, per_time');
            $table->string('category')->comment('区分: base, addition, subtraction');
            $table->json('conditions')->nullable()->comment('適用条件(JSON)');
            $table->date('valid_from')->comment('有効開始日');
            $table->date('valid_to')->nullable()->comment('有効終了日');
            $table->timestamps();

            $table->index(['service_type', 'category', 'valid_from']);
            $table->index(['service_code', 'valid_from']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_code_masters');
    }
};
