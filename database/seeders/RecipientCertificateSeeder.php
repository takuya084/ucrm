<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipientCertificateSeeder extends Seeder
{
    /**
     * 受給者証サンプルデータ
     * php artisan db:seed --class=RecipientCertificateSeeder
     */
    public function run(): void
    {
        $facilityId = DB::table('facilities')->value('id');
        if (!$facilityId) {
            $this->command->error('事業所が登録されていません。');
            return;
        }

        $children = DB::table('children')
            ->where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->get(['id']);

        if ($children->isEmpty()) {
            $this->command->error('児童が登録されていません。先に ChildrenSeeder を実行してください。');
            return;
        }

        $now = now();
        $inserted = 0;

        // 月支給量パターン（日数）
        $monthlyLimits = [10, 12, 15, 18, 20, 23];
        // 有効期間パターン
        $validPeriods = [
            ['from' => '2025-04-01', 'to' => '2026-03-31'],
            ['from' => '2025-07-01', 'to' => '2026-06-30'],
            ['from' => '2025-10-01', 'to' => '2026-09-30'],
            ['from' => '2026-01-01', 'to' => '2026-12-31'],
        ];
        $municipalities = ['渋谷区', '新宿区', '港区', '世田谷区', '杉並区'];

        foreach ($children as $i => $child) {
            // 既に受給者証があればスキップ
            $exists = DB::table('recipient_certificates')
                ->where('child_id', $child->id)
                ->exists();
            if ($exists) continue;

            $period = $validPeriods[$i % count($validPeriods)];
            $num    = str_pad($child->id, 8, '0', STR_PAD_LEFT);

            DB::table('recipient_certificates')->insert([
                'child_id'                    => $child->id,
                'certificate_number'          => "29{$num}",
                'municipality'                => $municipalities[$i % count($municipalities)],
                'valid_from'                  => $period['from'],
                'valid_to'                    => $period['to'],
                'monthly_limit'               => $monthlyLimits[$i % count($monthlyLimits)],
                'disability_support_category' => '放課後等デイサービス',
                'issue_date'                  => $period['from'],
                'status'                      => 'active',
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ]);
            $inserted++;
        }

        $this->command->info("受給者証データを {$inserted} 件登録しました。");
    }
}
