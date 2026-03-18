<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsageRecordSeeder extends Seeder
{
    /**
     * 出席記録サンプルデータ（過去2ヶ月分）
     * php artisan db:seed --class=UsageRecordSeeder
     */
    public function run(): void
    {
        $facilityId = DB::table('facilities')->value('id');
        if (!$facilityId) {
            $this->command->error('事業所が登録されていません。');
            return;
        }

        $staffIds = DB::table('staff')
            ->where('facility_id', $facilityId)
            ->pluck('id')
            ->toArray();

        if (empty($staffIds)) {
            $this->command->error('スタッフが登録されていません。');
            return;
        }

        // child_schedules から対象を取得
        $schedules = DB::table('child_schedules')
            ->join('children', 'child_schedules.child_id', '=', 'children.id')
            ->where('children.facility_id', $facilityId)
            ->where('children.contract_status', 'active')
            ->select('child_schedules.child_id', 'child_schedules.day_of_week', 'child_schedules.pickup_required')
            ->get();

        if ($schedules->isEmpty()) {
            $this->command->error('スケジュールが登録されていません。先に ChildScheduleSeeder を実行してください。');
            return;
        }

        // 曜日→数値マップ（date('N') は 1=月 〜 7=日）
        $dayMap = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

        $today = new \DateTime('2026-03-17');
        $start = new \DateTime('2026-01-05'); // 1月第1週月曜

        $inserted = 0;
        $current = clone $start;

        while ($current <= $today) {
            $dow = (int) $current->format('N'); // 1=月〜7=日
            $dateStr = $current->format('Y-m-d');

            foreach ($schedules as $schedule) {
                if (($dayMap[$schedule->day_of_week] ?? 0) !== $dow) {
                    continue;
                }

                $exists = DB::table('usage_records')
                    ->where('child_id', $schedule->child_id)
                    ->where('date', $dateStr)
                    ->exists();

                if ($exists) {
                    $current->modify('+1 day');
                    continue;
                }

                // ステータスをランダムに（出席80%, 欠席連絡15%, 無断欠席5%）
                $rand = rand(1, 100);
                if ($rand <= 80) {
                    $status = 'attended';
                    $absentReason = null;
                } elseif ($rand <= 95) {
                    $status = 'absent_notice';
                    $absentReason = ['発熱のため', '病院受診のため', '家庭の都合', '学校行事'][rand(0, 3)];
                } else {
                    $status = 'absent';
                    $absentReason = null;
                }

                $pickupDone = ($status === 'attended' && $schedule->pickup_required) ? 1 : 0;

                DB::table('usage_records')->insert([
                    'child_id'       => $schedule->child_id,
                    'facility_id'    => $facilityId,
                    'staff_id'       => $staffIds[array_rand($staffIds)],
                    'date'           => $dateStr,
                    'status'         => $status,
                    'absent_reason'  => $absentReason,
                    'pickup_done'    => $pickupDone,
                    'dropoff_done'   => $pickupDone,
                    'billing_target' => ($status === 'attended') ? 1 : 0,
                    'memo'           => null,
                    'created_at'     => $dateStr . ' 09:00:00',
                    'updated_at'     => $dateStr . ' 09:00:00',
                ]);
                $inserted++;
            }

            $current->modify('+1 day');
        }

        $this->command->info("出席記録を {$inserted} 件登録しました。");
    }
}
