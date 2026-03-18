<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChildScheduleSeeder extends Seeder
{
    /**
     * 児童週間スケジュールサンプルデータ
     * php artisan db:seed --class=ChildScheduleSeeder
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
            ->pluck('id')
            ->toArray();

        if (empty($children)) {
            $this->command->error('児童が登録されていません。先に ChildrenSeeder を実行してください。');
            return;
        }

        $now = now();
        $startDate = '2026-01-01';

        // 曜日パターン（児童ごとに割り当て）
        $patterns = [
            ['mon', 'wed', 'fri'],
            ['tue', 'thu'],
            ['mon', 'tue', 'wed', 'thu', 'fri'],
            ['mon', 'wed'],
            ['tue', 'thu', 'sat'],
            ['mon', 'tue', 'thu'],
            ['wed', 'fri'],
            ['mon', 'tue', 'wed'],
            ['thu', 'fri', 'sat'],
            ['mon', 'wed', 'fri', 'sat'],
        ];

        $inserted = 0;
        foreach ($children as $i => $childId) {
            $pattern = $patterns[$i % count($patterns)];
            foreach ($pattern as $day) {
                $exists = DB::table('child_schedules')
                    ->where('child_id', $childId)
                    ->where('day_of_week', $day)
                    ->where('start_date', $startDate)
                    ->exists();

                if (!$exists) {
                    DB::table('child_schedules')->insert([
                        'child_id'        => $childId,
                        'day_of_week'     => $day,
                        'start_date'      => $startDate,
                        'end_date'        => null,
                        'status'          => 'regular',
                        'pickup_required' => ($i % 3 === 0) ? 1 : 0,
                        'memo'            => null,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]);
                    $inserted++;
                }
            }
        }

        $this->command->info("児童スケジュールを {$inserted} 件登録しました。");
    }
}
