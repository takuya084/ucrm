<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::getDatabaseName();

        $hasType = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = ? AND table_name = 'copayment_cap_details'
               AND column_name = 'billable_facility_type'",
            [$db]
        )->c;

        if (!$hasType) {
            DB::statement("ALTER TABLE copayment_cap_details
                ADD COLUMN billable_facility_type VARCHAR(255) NULL,
                ADD COLUMN billable_facility_id BIGINT UNSIGNED NULL");
        }

        $hasIndex = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = 'copayment_cap_details'
               AND index_name = 'cap_detail_billable_idx'",
            [$db]
        )->c;

        if (!$hasIndex) {
            DB::statement("ALTER TABLE copayment_cap_details
                ADD INDEX cap_detail_billable_idx (billable_facility_type, billable_facility_id)");
        }

        // 既存データを自社 Facility として morph に移行
        DB::table('copayment_cap_details')
            ->whereNotNull('facility_id')
            ->whereNull('billable_facility_type')
            ->update([
                'billable_facility_type' => \App\Models\Facility::class,
                'billable_facility_id'   => DB::raw('facility_id'),
            ]);

        // facility_id を nullable化 + FK削除（raw）
        $fkName = DB::selectOne(
            "SELECT constraint_name AS name FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = 'copayment_cap_details'
               AND column_name = 'facility_id' AND referenced_table_name = 'facilities'
             LIMIT 1",
            [$db]
        );
        if ($fkName && $fkName->name) {
            DB::statement("ALTER TABLE copayment_cap_details DROP FOREIGN KEY `{$fkName->name}`");
        }

        DB::statement("ALTER TABLE copayment_cap_details MODIFY facility_id BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        Schema::table('copayment_cap_details', function ($table) {
            $table->dropIndex('cap_detail_billable_idx');
            $table->dropColumn(['billable_facility_type', 'billable_facility_id']);
        });
    }
};
