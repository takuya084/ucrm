<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7)->comment('YYYY-MM');
            $table->string('status')->default('draft')->comment('draft, calculating, confirmed, submitted, completed, error');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'year_month']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('billing_periods');
    }
};
