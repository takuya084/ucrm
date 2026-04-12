<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * 請求機能テスト用ダミーデータ
 *
 * php artisan db:seed --class=BillingTestDataSeeder
 *
 * 前提: InitialDataSeeder, ShiftSampleSeeder, ServiceCodeMasterSeeder 済み
 */
class BillingTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now()->format('Y-m-d H:i:s');
        $facilityId = DB::table('facilities')->value('id');

        if (!$facilityId) {
            $this->command->error('事業所が見つかりません。先に InitialDataSeeder を実行してください。');
            return;
        }

        // ====================================================
        // 1. 施設の請求設定を更新
        // ====================================================
        DB::table('facilities')->where('id', $facilityId)->update([
            'facility_code'      => '1350000001',
            'service_type'       => 'houday',
            'area_unit_price'    => 10.00,   // 6級地
            'designated_date'    => '2020-04-01',
            'administrator_name' => '管理者 太郎',
            'fax'                => '03-1234-5679',
            'capacity_per_day'   => 10,
            'updated_at'         => $now,
        ]);
        $this->command->info('✓ 施設の請求設定を更新しました');

        // ====================================================
        // 2. 児童に受給者証（請求用カラム入り）を作成
        // ====================================================
        $children = DB::table('children')
            ->where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get();

        if ($children->isEmpty()) {
            $this->command->error('児童データがありません。ShiftSampleSeeder を先に実行してください。');
            return;
        }

        // 上限月額パターン（世帯所得区分ごと）
        $capPatterns = [
            ['cap' => 4600,  'label' => '低所得'],         // 0-3
            ['cap' => 4600,  'label' => '低所得'],
            ['cap' => 4600,  'label' => '低所得'],
            ['cap' => 4600,  'label' => '低所得'],
            ['cap' => 37200, 'label' => '一般1'],          // 4-7
            ['cap' => 37200, 'label' => '一般1'],
            ['cap' => 37200, 'label' => '一般1'],
            ['cap' => 37200, 'label' => '一般1'],
            ['cap' => 0,     'label' => '非課税世帯'],     // 8-9
            ['cap' => 0,     'label' => '非課税世帯'],
            ['cap' => 4600,  'label' => '低所得'],         // 10-11
            ['cap' => 37200, 'label' => '一般1'],
        ];

        // 既存の受給者証を削除して再作成
        $childIds = $children->pluck('id')->toArray();
        DB::table('recipient_certificates')->whereIn('child_id', $childIds)->delete();

        foreach ($children->values() as $i => $child) {
            $pattern = $capPatterns[$i % count($capPatterns)];
            // 2人目を上限管理対象（複数事業所利用想定）にする
            $isCapTarget = ($i === 1);

            DB::table('recipient_certificates')->insert([
                'child_id'                  => $child->id,
                'certificate_number'        => sprintf('26%08d', 10000 + $i),
                'municipality'              => '渋谷区',
                'municipality_code'         => '131130',
                'valid_from'                => '2026-04-01',
                'valid_to'                  => '2027-03-31',
                'monthly_limit'             => 23,
                'disability_support_category' => '放課後等デイサービス',
                'issue_date'                => '2026-03-15',
                'status'                    => 'active',
                'copayment_rate'            => 10,
                'copayment_cap_monthly'     => $pattern['cap'],
                'is_cap_management_target'  => $isCapTarget,
                'cap_managing_facility_id'  => $isCapTarget ? $facilityId : null,
                'service_type'              => 'houday',
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);
        }
        $this->command->info('✓ 受給者証を作成しました（' . $children->count() . '件）');

        // ====================================================
        // 3. 加算・減算設定（FacilityServiceSettings）
        // ====================================================
        DB::table('facility_service_settings')->where('facility_id', $facilityId)->delete();

        // 有効にする加算コード
        $enableCodes = [
            '636100',  // 送迎加算（迎え）
            '636101',  // 送迎加算（送り）
            '636200',  // 延長支援加算（1時間未満）
            '636300',  // 欠席時対応加算
            '636401',  // 児童指導員等加配加算（児童指導員等）
        ];

        $codeRecords = DB::table('service_code_masters')
            ->whereIn('service_code', $enableCodes)
            ->where('service_type', 'houday')
            ->get();

        foreach ($codeRecords as $code) {
            DB::table('facility_service_settings')->insert([
                'facility_id'            => $facilityId,
                'service_code_master_id' => $code->id,
                'is_enabled'             => true,
                'effective_from'         => '2024-04-01',
                'effective_to'           => null,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
        $this->command->info('✓ 加算設定を登録しました（' . $codeRecords->count() . '件）');

        // ====================================================
        // 4. 2026年4月の出席記録（請求用フィールド入り）
        // ====================================================
        $yearMonth = '2026-04';
        $staffId   = DB::table('staff')->where('facility_id', $facilityId)->value('id');

        // 既存の4月の出席記録を削除
        DB::table('usage_records')
            ->where('facility_id', $facilityId)
            ->where('date', 'like', $yearMonth . '%')
            ->delete();

        // 4月の平日を取得（1日〜10日 + 数日で十分なテストデータ）
        $period = CarbonPeriod::create("{$yearMonth}-01", "{$yearMonth}-10");
        $schoolDays = [];
        $holidays   = [];

        foreach ($period as $day) {
            if ($day->isWeekday()) {
                $schoolDays[] = $day->toDateString();
            } elseif ($day->isSaturday()) {
                $holidays[] = $day->toDateString(); // 土曜＝休業日扱い
            }
        }

        // 児童ごとの利用曜日パターン（既存スケジュールを利用）
        $schedules = DB::table('child_schedules')
            ->whereIn('child_id', $childIds)
            ->get()
            ->groupBy('child_id');

        $recordCount = 0;

        // 来所・退所時間のパターン
        $schoolDayTimes = [
            ['in' => '14:30', 'out' => '17:30'],
            ['in' => '14:00', 'out' => '17:00'],
            ['in' => '15:00', 'out' => '17:30'],
            ['in' => '14:30', 'out' => '18:30'], // 延長あり
        ];
        $holidayTimes = [
            ['in' => '09:30', 'out' => '16:00'],
            ['in' => '10:00', 'out' => '16:30'],
            ['in' => '09:00', 'out' => '15:30'],
        ];

        $dayMap = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 0 => 'sun'];

        foreach ($children as $ci => $child) {
            $childScheduleDows = ($schedules[$child->id] ?? collect())->pluck('day_of_week')->toArray();
            $isPickupChild = ($ci % 3 === 0); // ShiftSampleSeeder と同じパターン

            // 学校日
            foreach ($schoolDays as $di => $date) {
                $dow = $dayMap[Carbon::parse($date)->dayOfWeek];
                if (!in_array($dow, $childScheduleDows)) continue;

                $times = $schoolDayTimes[($ci + $di) % count($schoolDayTimes)];
                $isExtension = ($times['out'] > '18:00');

                // 1人目の4/7は連絡あり欠席（欠席時対応加算テスト用）
                $isAbsent = ($ci === 0 && $date === "{$yearMonth}-07");

                DB::table('usage_records')->insert([
                    'child_id'       => $child->id,
                    'facility_id'    => $facilityId,
                    'staff_id'       => $staffId,
                    'date'           => $date,
                    'status'         => $isAbsent ? 'absent_notice' : 'attended',
                    'absent_reason'  => $isAbsent ? '体調不良（保護者連絡あり）' : null,
                    'pickup_done'    => !$isAbsent && $isPickupChild,
                    'dropoff_done'   => !$isAbsent && $isPickupChild,
                    'billing_target' => true,
                    'memo'           => null,
                    'check_in_time'  => $isAbsent ? null : $times['in'],
                    'check_out_time' => $isAbsent ? null : $times['out'],
                    'is_school_day'  => true,
                    'service_type'   => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $recordCount++;
            }

            // 休業日（土曜）
            foreach ($holidays as $di => $date) {
                $dow = $dayMap[Carbon::parse($date)->dayOfWeek];
                if (!in_array($dow, $childScheduleDows)) continue;

                $times = $holidayTimes[($ci + $di) % count($holidayTimes)];

                DB::table('usage_records')->insert([
                    'child_id'       => $child->id,
                    'facility_id'    => $facilityId,
                    'staff_id'       => $staffId,
                    'date'           => $date,
                    'status'         => 'attended',
                    'absent_reason'  => null,
                    'pickup_done'    => $isPickupChild,
                    'dropoff_done'   => $isPickupChild,
                    'billing_target' => true,
                    'memo'           => null,
                    'check_in_time'  => $times['in'],
                    'check_out_time' => $times['out'],
                    'is_school_day'  => false,
                    'service_type'   => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $recordCount++;
            }
        }

        $this->command->info('✓ 出席記録を作成しました（' . $recordCount . '件）');

        // ====================================================
        // 5. 保護者データ（請求書に必要）
        //    guardians テーブル + child_guardian_relations 中間テーブル
        // ====================================================
        $existingRelations = DB::table('child_guardian_relations')
            ->whereIn('child_id', $childIds)
            ->pluck('child_id')
            ->unique()
            ->toArray();
        $guardianCount = 0;

        foreach ($children as $child) {
            if (in_array($child->id, $existingRelations)) continue;

            $lastName = mb_substr($child->name, 0, mb_strpos($child->name, ' '));
            $guardianId = DB::table('guardians')->insertGetId([
                'name'           => $lastName . ' 保護者',
                'name_kana'      => null,
                'relationship'   => 'mother',
                'tel_primary'    => '090-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'email'          => null,
                'address'        => '東京都渋谷区' . rand(1, 5) . '-' . rand(1, 20) . '-' . rand(1, 10),
                'postcode'       => '150-00' . str_pad(rand(10, 99), 2, '0', STR_PAD_LEFT),
                'preferred_contact' => 'tel',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            DB::table('child_guardian_relations')->insert([
                'child_id'             => $child->id,
                'guardian_id'          => $guardianId,
                'is_primary'           => true,
                'is_emergency_contact' => true,
                'priority_order'       => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $guardianCount++;
        }
        if ($guardianCount > 0) {
            $this->command->info('✓ 保護者を作成しました（' . $guardianCount . '件）');
        }

        // ====================================================
        // サマリー
        // ====================================================
        $this->command->newLine();
        $this->command->info('=== 請求テストデータ投入完了 ===');
        $this->command->info("対象月: {$yearMonth}");
        $this->command->info("児童数: {$children->count()}名");
        $this->command->info("出席記録: {$recordCount}件");
        $this->command->newLine();
        $this->command->info('【次のステップ】');
        $this->command->info('1. leader@example.com でログイン');
        $this->command->info('2. 請求管理 → 月次請求');
        $this->command->info('3. 年月「2026-04」を選択して「計算実行」');
        $this->command->info('4. 児童ごとの明細・実績記録票を確認');
        $this->command->info('5. 確定 → CSV出力 → 利用者請求書PDF生成');
    }
}
