<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copayment_cap_details', function (Blueprint $table) {
            $table->nullableMorphs('billable_facility');
        });

        // 既存データを自社 Facility として morph カラムに移行
        DB::table('copayment_cap_details')
            ->whereNotNull('facility_id')
            ->update([
                'billable_facility_type' => \App\Models\Facility::class,
                'billable_facility_id'   => DB::raw('facility_id'),
            ]);

        Schema::table('copayment_cap_details', function (Blueprint $table) {
            $table->dropForeign(['facility_id']);
            $table->unsignedBigInteger('facility_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('copayment_cap_details', function (Blueprint $table) {
            $table->dropMorphs('billable_facility');
            $table->foreign('facility_id')->references('id')->on('facilities');
        });
    }
};
