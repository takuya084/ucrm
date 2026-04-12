<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('copayment_cap_managements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->foreignId('managing_facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->integer('cap_amount')->comment('上限月額');
            $table->integer('total_copayment')->default(0)->comment('全事業所合計負担額');
            $table->integer('adjusted_copayment')->default(0)->comment('調整後負担額');
            $table->string('management_result')->default('1')
                ->comment('1:管理結果なし, 2:管理結果あり, 3:管理結果あり(按分)');
            $table->string('status')->default('draft')->comment('draft, confirmed, submitted');
            $table->timestamps();

            $table->unique(['child_id', 'year_month', 'managing_facility_id'], 'cap_mgmt_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('copayment_cap_managements');
    }
};
