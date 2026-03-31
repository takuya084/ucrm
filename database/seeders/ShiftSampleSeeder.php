<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShiftSampleSeeder extends Seeder
{
    /**
     * シフト関連のサンプルデータ
     * php artisan db:seed --class=ShiftSampleSeeder で実行
     * ※ InitialDataSeeder 実行後に使うこと
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $facilityId = DB::table('facilities')->value('id');
        $staffMembers = DB::table('staff')
            ->where('facility_id', $facilityId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($staffMembers->isEmpty()) {
            $this->command->error('スタッフが見つかりません。InitialDataSeeder を先に実行してください。');
            return;
        }

        // ─── シフトラベル ─────────────────────────────
        DB::table('shift_labels')->where('facility_id', $facilityId)->delete();
        DB::table('shift_labels')->insert([
            ['facility_id' => $facilityId, 'name' => '早番',  'is_off' => false, 'display_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['facility_id' => $facilityId, 'name' => '遅番',  'is_off' => false, 'display_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['facility_id' => $facilityId, 'name' => '送迎',  'is_off' => false, 'display_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['facility_id' => $facilityId, 'name' => '休み',  'is_off' => true,  'display_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['facility_id' => $facilityId, 'name' => '有給',  'is_off' => true,  'display_order' => 91, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->command->info('シフトラベルを登録しました（早番, 遅番, 送迎, 休み, 有給）。');

        // ─── 追加スタッフ（5名追加して合計8名に）──────────────
        $extraStaff = [
            ['name' => '鈴木 美咲',  'role' => 'staff',  'email' => 'suzuki@example.com'],
            ['name' => '田中 健太',  'role' => 'staff',  'email' => 'tanaka@example.com'],
            ['name' => '佐藤 あゆみ', 'role' => 'staff', 'email' => 'sato@example.com'],
            ['name' => '山本 大輔',  'role' => 'driver', 'email' => 'yamamoto@example.com'],
            ['name' => '中村 さくら', 'role' => 'staff', 'email' => 'nakamura@example.com'],
        ];

        foreach ($extraStaff as $i => $s) {
            $exists = DB::table('users')->where('email', $s['email'])->exists();
            if ($exists) continue;

            $userId = DB::table('users')->insertGetId([
                'name'              => $s['name'],
                'email'             => $s['email'],
                'password'          => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            DB::table('staff')->insert([
                'user_id'       => $userId,
                'facility_id'   => $facilityId,
                'name'          => $s['name'],
                'role'          => $s['role'],
                'is_active'     => true,
                'display_order' => $i + 4,
                'joined_at'     => '2025-04-01',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // スタッフ再取得
        $staffMembers = DB::table('staff')
            ->where('facility_id', $facilityId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        // ─── スタッフ資格 ─────────────────────────────
        DB::table('staff_qualifications')->whereIn('staff_id', $staffMembers->pluck('id'))->delete();

        $qualMap = [];
        foreach ($staffMembers->values() as $idx => $staff) {
            $quals = match ($idx) {
                0 => ['jidou_shidouin'],                    // 管理者太郎
                1 => ['hoikushi'],                           // 支援責任者花子
                2 => ['hoikushi'],                           // 現場職員次郎
                3 => ['jidou_shidouin'],                    // 鈴木美咲
                4 => [],                                     // 田中健太
                5 => ['pt'],                                 // 佐藤あゆみ
                6 => ['sougeikanou'],                       // 山本大輔
                7 => ['jidou_shidouin'],                    // 中村さくら
                default => [],
            };
            foreach ($quals as $q) {
                DB::table('staff_qualifications')->insert([
                    'staff_id'      => $staff->id,
                    'qualification' => $q,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
        $this->command->info('スタッフ資格を登録しました。');

        // ─── 利用児童（サンプル12名）──────────────────────
        $sampleChildren = [
            ['name' => '山田 太一',   'name_kana' => 'ヤマダ タイチ',     'gender' => 'male',   'birthdate' => '2018-05-12', 'grade' => '小2', 'allergy_note' => null],
            ['name' => '鈴木 花',     'name_kana' => 'スズキ ハナ',       'gender' => 'female', 'birthdate' => '2017-08-03', 'grade' => '小3', 'allergy_note' => '卵・乳製品'],
            ['name' => '佐藤 翔太',   'name_kana' => 'サトウ ショウタ',   'gender' => 'male',   'birthdate' => '2019-01-20', 'grade' => '小1', 'allergy_note' => null],
            ['name' => '田中 美優',   'name_kana' => 'タナカ ミユ',       'gender' => 'female', 'birthdate' => '2017-11-15', 'grade' => '小3', 'allergy_note' => '小麦（アナフィラキシー既往あり・エピペン携帯）'],
            ['name' => '高橋 蓮',     'name_kana' => 'タカハシ レン',     'gender' => 'male',   'birthdate' => '2018-03-28', 'grade' => '小2', 'allergy_note' => null],
            ['name' => '伊藤 さくら', 'name_kana' => 'イトウ サクラ',     'gender' => 'female', 'birthdate' => '2019-07-07', 'grade' => '小1', 'allergy_note' => 'そば・ピーナッツ'],
            ['name' => '渡辺 悠斗',   'name_kana' => 'ワタナベ ユウト',   'gender' => 'male',   'birthdate' => '2016-12-01', 'grade' => '小4', 'allergy_note' => null],
            ['name' => '中村 陽菜',   'name_kana' => 'ナカムラ ヒナ',     'gender' => 'female', 'birthdate' => '2018-09-10', 'grade' => '小2', 'allergy_note' => null],
            ['name' => '小林 大翔',   'name_kana' => 'コバヤシ ヒロト',   'gender' => 'male',   'birthdate' => '2017-04-22', 'grade' => '小3', 'allergy_note' => null],
            ['name' => '加藤 凛',     'name_kana' => 'カトウ リン',       'gender' => 'female', 'birthdate' => '2019-06-18', 'grade' => '小1', 'allergy_note' => '甲殻類（エビ・カニ）'],
            ['name' => '吉田 湊',     'name_kana' => 'ヨシダ ミナト',     'gender' => 'male',   'birthdate' => '2018-02-14', 'grade' => '小2', 'allergy_note' => null],
            ['name' => '松本 結衣',   'name_kana' => 'マツモト ユイ',     'gender' => 'female', 'birthdate' => '2016-10-30', 'grade' => '小4', 'allergy_note' => null],
        ];

        // 既存児童を削除して再作成
        $existingChildIds = DB::table('children')->where('facility_id', $facilityId)->pluck('id');
        if ($existingChildIds->isNotEmpty()) {
            DB::table('child_schedules')->whereIn('child_id', $existingChildIds)->delete();
            DB::table('children')->where('facility_id', $facilityId)->delete();
        }

        $childIds = [];
        foreach ($sampleChildren as $c) {
            $childIds[] = DB::table('children')->insertGetId([
                'facility_id'      => $facilityId,
                'name'             => $c['name'],
                'name_kana'        => $c['name_kana'],
                'gender'           => $c['gender'],
                'birthdate'        => $c['birthdate'],
                'grade'            => $c['grade'],
                'allergy_note'     => $c['allergy_note'],
                'contract_status'  => 'active',
                'contract_start_date' => '2025-04-01',
                'pickup_required'  => false,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
        $this->command->info('利用児童を登録しました（' . count($childIds) . '名）。');

        // ─── 利用児童スケジュール（曜日別に配置をバラす）────────
        // 曜日ごとの利用人数が変わるよう意図的にパターンを分散
        // → 月:8名, 火:7名, 水:9名, 木:7名, 金:10名, 土:4名 程度
        $dayPatterns = [
            ['mon', 'tue', 'wed', 'thu', 'fri'],              // 山田太一: 週5
            ['mon', 'wed', 'fri'],                              // 鈴木花: 週3
            ['tue', 'thu', 'sat'],                              // 佐藤翔太: 週3
            ['mon', 'tue', 'thu', 'fri'],                       // 田中美優: 週4
            ['mon', 'wed', 'thu'],                              // 高橋蓮: 週3
            ['tue', 'wed', 'fri', 'sat'],                       // 伊藤さくら: 週4
            ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],        // 渡辺悠斗: 週6
            ['wed', 'thu', 'fri'],                              // 中村陽菜: 週3
            ['mon', 'wed', 'fri', 'sat'],                       // 小林大翔: 週4
            ['mon', 'tue', 'wed', 'fri'],                       // 加藤凛: 週4
            ['mon', 'tue', 'wed', 'thu', 'fri'],               // 吉田湊: 週5
            ['tue', 'wed', 'thu', 'fri', 'sat'],               // 松本結衣: 週5
        ];

        foreach ($childIds as $ci => $childId) {
            $pattern = $dayPatterns[$ci] ?? $dayPatterns[$ci % count($dayPatterns)];
            foreach ($pattern as $dow) {
                DB::table('child_schedules')->insert([
                    'child_id'        => $childId,
                    'day_of_week'     => $dow,
                    'start_date'      => '2025-04-01',
                    'end_date'        => null,
                    'status'          => 'regular',
                    'pickup_required' => $ci % 3 === 0,
                    'memo'            => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
        $this->command->info('利用児童スケジュールを登録しました。');

        // ─── 基本勤務パターン ──────────────────────────
        // [time, label] のペア。null=休み
        $patterns = [
            // 管理者 太郎（早番）
            [['09:00','早番'],['09:00','早番'],['09:00','早番'],['09:00','早番'],['09:00','早番'], null, null],
            // 支援責任者 花子（早番、土曜も出勤）
            [['09:00','早番'],['09:00','早番'],['09:00','早番'],['09:00','早番'],['09:00','早番'],['09:00','早番'], null],
            // 現場職員 次郎（遅番）
            [['10:00','遅番'],['10:00','遅番'],['10:00','遅番'],['10:00','遅番'],['10:00','遅番'], null, null],
            // 鈴木 美咲（早番、水曜休み）
            [['09:30','早番'],['09:30','早番'], null, ['09:30','早番'],['09:30','早番'],['09:30','早番'], null],
            // 田中 健太（遅番）
            [['10:00','遅番'],['10:00','遅番'],['10:00','遅番'],['10:00','遅番'],['10:00','遅番'], null, null],
            // 佐藤 あゆみ（月曜休み）
            [null, ['09:00','早番'],['09:00','早番'],['09:00','早番'],['09:00','早番'], null, null],
            // 山本 大輔（送迎）
            [['13:00','送迎'],['13:00','送迎'],['13:00','送迎'],['13:00','送迎'],['13:00','送迎'],['10:00','早番'], null],
            // 中村 さくら（火木休み）
            [['09:00','早番'], null, ['09:00','早番'], null, ['09:00','早番'],['09:00','早番'], null],
        ];

        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        DB::table('staff_work_patterns')->where('facility_id', $facilityId)->delete();

        foreach ($staffMembers->values() as $idx => $staff) {
            if (!isset($patterns[$idx])) continue;
            foreach ($days as $di => $day) {
                $p = $patterns[$idx][$di];
                $time = is_array($p) ? $p[0] : null;
                $label = is_array($p) ? $p[1] : ($p === null ? '休み' : '');
                DB::table('staff_work_patterns')->insert([
                    'staff_id'    => $staff->id,
                    'facility_id' => $facilityId,
                    'day_of_week' => $day,
                    'start_time'  => $time,
                    'work_type'   => $label,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        $this->command->info('勤務パターンを登録しました。');

        // ─── 月次シフト（今月分）を自動生成 ────────────────
        $year  = (int) date('Y');
        $month = (int) date('m');

        DB::table('shift_day_notes')->whereIn(
            'monthly_shift_id',
            DB::table('monthly_shifts')->where('facility_id', $facilityId)->pluck('id')
        )->delete();
        DB::table('shift_entries')->whereIn(
            'monthly_shift_id',
            DB::table('monthly_shifts')->where('facility_id', $facilityId)->pluck('id')
        )->delete();
        DB::table('monthly_shifts')->where('facility_id', $facilityId)->delete();

        $createdBy = $staffMembers->firstWhere('role', 'admin')?->id;

        $shiftId = DB::table('monthly_shifts')->insertGetId([
            'facility_id' => $facilityId,
            'year'        => $year,
            'month'       => $month,
            'status'      => 'draft',
            'created_by'  => $createdBy,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $dowMap = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat'];
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        $allPatterns = DB::table('staff_work_patterns')
            ->where('facility_id', $facilityId)
            ->get()
            ->groupBy('staff_id');

        $entries = [];
        foreach (range(1, $daysInMonth) as $day) {
            $date = Carbon::create($year, $month, $day);
            $dow  = $dowMap[$date->dayOfWeek];

            foreach ($staffMembers as $staff) {
                $p = $allPatterns->get($staff->id)?->firstWhere('day_of_week', $dow);
                $entries[] = [
                    'monthly_shift_id' => $shiftId,
                    'staff_id'         => $staff->id,
                    'date'             => $date->toDateString(),
                    'start_time'       => $p?->start_time,
                    'work_type'        => $p?->work_type ?? ($dow === 'sun' ? '休み' : ''),
                    'note'             => null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }
        DB::table('shift_entries')->insert($entries);

        // ─── 例外データ（警告パターン確認用）────────────────
        $adminStaff  = $staffMembers->firstWhere('role', 'admin');
        $leaderStaff = $staffMembers->firstWhere('role', 'leader');
        $staffJiro   = $staffMembers->firstWhere('name', '現場職員 次郎');
        $staffMisaki = $staffMembers->firstWhere('name', '鈴木 美咲');
        $staffKenta  = $staffMembers->firstWhere('name', '田中 健太');
        $staffAyumi  = $staffMembers->firstWhere('name', '佐藤 あゆみ');

        $overrides = [];

        // ── パターンA: 15日(水) — 管理者太郎が有給 → 通常の有給表示
        if ($adminStaff && $daysInMonth >= 15) {
            $overrides[] = ['staff_id' => $adminStaff->id, 'day' => 15, 'work_type' => '有給'];
        }

        // ── パターンB: 20日(金) — 大量休みで「職員不足」警告
        //    次郎・美咲・健太・あゆみを休みにする → 配置4名減
        foreach ([$staffJiro, $staffMisaki, $staffKenta, $staffAyumi] as $s) {
            if ($s && $daysInMonth >= 20) {
                $overrides[] = ['staff_id' => $s->id, 'day' => 20, 'work_type' => '休み'];
            }
        }

        // ── パターンC: 22日(日は休みなので) 23日(月) — 児発管（leader）が休み → 「児発管不在」警告
        if ($leaderStaff && $daysInMonth >= 23) {
            $overrides[] = ['staff_id' => $leaderStaff->id, 'day' => 23, 'work_type' => '休み'];
        }

        // ── パターンD: 25日(水) — 有資格者を休みにして「有資格者不足」警告
        //    保育士の花子(leader)と次郎なし、児童指導員の美咲も休み
        if ($leaderStaff && $daysInMonth >= 25) {
            $overrides[] = ['staff_id' => $leaderStaff->id, 'day' => 25, 'work_type' => '休み'];
        }
        if ($staffMisaki && $daysInMonth >= 25) {
            $overrides[] = ['staff_id' => $staffMisaki->id, 'day' => 25, 'work_type' => '有給'];
        }

        foreach ($overrides as $o) {
            DB::table('shift_entries')
                ->where('monthly_shift_id', $shiftId)
                ->where('staff_id', $o['staff_id'])
                ->where('date', Carbon::create($year, $month, $o['day'])->toDateString())
                ->update(['work_type' => $o['work_type'], 'start_time' => null]);
        }

        // ─── 日別備考 ──────────────────────────────
        $dayNoteData = [
            1  => '月初ミーティング',
            3  => '個別支援計画見直し',
            5  => '身体測定',
            7  => '外部講師来訪（音楽療法）',
            10 => '避難訓練（地震想定）',
            12 => 'モニタリング会議',
            14 => '保護者面談（田中様・佐藤様）',
            15 => '給食メニュー変更あり',
            18 => '保護者会 15:00〜',
            20 => '施設内研修（強度行動障害）',
            22 => '外出活動（公園）',
            25 => 'お楽しみ会（誕生日会）',
            28 => '月末事務処理・翌月準備',
        ];

        $dayNotes = [];
        foreach ($dayNoteData as $day => $note) {
            if ($daysInMonth >= $day) {
                $dayNotes[] = [
                    'monthly_shift_id' => $shiftId,
                    'date'             => Carbon::create($year, $month, $day)->toDateString(),
                    'note'             => $note,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }
        if (!empty($dayNotes)) {
            DB::table('shift_day_notes')->insert($dayNotes);
        }

        $noteCount = count($dayNotes);
        $this->command->info("{$year}年{$month}月のシフト表を作成しました（スタッフ{$staffMembers->count()}名）。");
        $this->command->info("日別備考: {$noteCount}件登録しました。");
        $this->command->info('例外: 15日=管理者有給, 20日=職員不足, 23日=児発管不在, 25日=有資格者不足');
    }
}
