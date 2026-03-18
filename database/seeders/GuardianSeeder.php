<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GuardianSeeder extends Seeder
{
    /**
     * 保護者サンプルデータ
     * php artisan db:seed --class=GuardianSeeder
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
            ->get(['id', 'name', 'name_kana']);

        if ($children->isEmpty()) {
            $this->command->error('児童が登録されていません。先に ChildrenSeeder を実行してください。');
            return;
        }

        $now = now();
        $inserted = 0;

        $relationships  = ['mother', 'father', 'mother', 'mother', 'father'];
        $prefixes       = ['090', '080', '070'];
        $contactMethods = ['tel', 'line', 'tel', 'tel', 'email'];

        // 苗字リスト（児童と同じ苗字を使う）
        $lastNames = ['田中', '鈴木', '佐藤', '山田', '伊藤', '渡辺', '中村', '小林', '加藤', '吉田',
                      '山本', '松本', '井上', '木村', '林', '斎藤', '清水', '山口', '高橋', '石川'];

        foreach ($children as $i => $child) {
            // 既に保護者が登録されていればスキップ
            $exists = DB::table('child_guardian_relations')
                ->where('child_id', $child->id)
                ->exists();
            if ($exists) continue;

            $relationship = $relationships[$i % count($relationships)];
            $lastName = explode(' ', $child->name)[0] ?? $lastNames[$i % count($lastNames)];
            $firstName = $relationship === 'mother' ? '花子' : '太郎';
            $guardianName = $lastName . ' ' . $firstName;

            // 電話番号生成
            $prefix = $prefixes[$i % count($prefixes)];
            $tel = $prefix . '-' . str_pad(rand(1000, 9999), 4, '0') . '-' . str_pad(rand(1000, 9999), 4, '0');

            $guardianId = DB::table('guardians')->insertGetId([
                'name'              => $guardianName,
                'name_kana'         => null,
                'relationship'      => $relationship,
                'tel_primary'       => $tel,
                'tel_secondary'     => null,
                'email'             => null,
                'line_id'           => null,
                'preferred_contact' => $contactMethods[$i % count($contactMethods)],
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            DB::table('child_guardian_relations')->insert([
                'child_id'            => $child->id,
                'guardian_id'         => $guardianId,
                'is_primary'          => 1,
                'is_emergency_contact'=> 1,
                'priority_order'      => 1,
                'memo'                => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            $inserted++;
        }

        $this->command->info("保護者データを {$inserted} 件登録しました。");
    }
}
