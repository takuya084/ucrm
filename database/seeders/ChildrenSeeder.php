<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChildrenSeeder extends Seeder
{
    /**
     * 利用児童サンプルデータ（30名）
     * php artisan db:seed --class=ChildrenSeeder
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $facilityId = DB::table('facilities')->value('id');
        if (!$facilityId) {
            $this->command->error('事業所が登録されていません。先に InitialDataSeeder を実行してください。');
            return;
        }

        // ─── 学校 ─────────────────────────────────────────────
        $schoolIds = [];
        $schools = [
            ['name' => '○○市立第一小学校', 'name_kana' => 'まるまるしりつだいいちしょうがっこう', 'school_type' => 'elementary', 'end_time_regular' => '15:00', 'end_time_early' => '12:00'],
            ['name' => '○○市立第二小学校', 'name_kana' => 'まるまるしりつだいにしょうがっこう',   'school_type' => 'elementary', 'end_time_regular' => '15:10', 'end_time_early' => '12:00'],
            ['name' => '○○市立第三小学校', 'name_kana' => 'まるまるしりつだいさんしょうがっこう', 'school_type' => 'elementary', 'end_time_regular' => '14:50', 'end_time_early' => '11:50'],
            ['name' => '○○市立第一中学校', 'name_kana' => 'まるまるしりつだいいちちゅうがっこう', 'school_type' => 'junior_high', 'end_time_regular' => '16:00', 'end_time_early' => '12:30'],
            ['name' => '△△特別支援学校',  'name_kana' => 'さんかくさんとくべつしえんがっこう',   'school_type' => 'special_needs','end_time_regular' => '14:30', 'end_time_early' => '12:00'],
        ];

        foreach ($schools as $school) {
            $existing = DB::table('schools')->where('name', $school['name'])->value('id');
            if ($existing) {
                $schoolIds[] = $existing;
            } else {
                $schoolIds[] = DB::table('schools')->insertGetId(array_merge($school, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // ─── 児童 30名 ────────────────────────────────────────
        $children = [
            // 小学生（低学年）
            ['name' => '田中 ひなた',   'name_kana' => 'たなか ひなた',   'gender' => 'female', 'birthdate' => '2019-04-15', 'grade' => '小1', 'school_id' => $schoolIds[0], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => true,  'contract_start_date' => '2025-04-01'],
            ['name' => '鈴木 こうた',   'name_kana' => 'すずき こうた',   'gender' => 'male',   'birthdate' => '2019-06-20', 'grade' => '小1', 'school_id' => $schoolIds[0], 'disability_type' => 'ADHD',             'pickup_required' => false, 'contract_start_date' => '2025-04-01'],
            ['name' => '佐藤 みおん',   'name_kana' => 'さとう みおん',   'gender' => 'female', 'birthdate' => '2018-08-03', 'grade' => '小2', 'school_id' => $schoolIds[1], 'disability_type' => '知的障がい',       'pickup_required' => true,  'contract_start_date' => '2025-06-01'],
            ['name' => '伊藤 りく',     'name_kana' => 'いとう りく',     'gender' => 'male',   'birthdate' => '2018-11-25', 'grade' => '小2', 'school_id' => $schoolIds[1], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => true,  'contract_start_date' => '2025-04-01'],
            ['name' => '渡辺 あおい',   'name_kana' => 'わたなべ あおい', 'gender' => 'female', 'birthdate' => '2017-05-10', 'grade' => '小3', 'school_id' => $schoolIds[0], 'disability_type' => 'ADHD',             'pickup_required' => false, 'contract_start_date' => '2024-10-01'],
            ['name' => '山本 そうた',   'name_kana' => 'やまもと そうた', 'gender' => 'male',   'birthdate' => '2017-09-18', 'grade' => '小3', 'school_id' => $schoolIds[2], 'disability_type' => '発達障がい',       'pickup_required' => true,  'contract_start_date' => '2025-01-01'],

            // 小学生（中学年）
            ['name' => '中村 ゆい',     'name_kana' => 'なかむら ゆい',   'gender' => 'female', 'birthdate' => '2016-03-07', 'grade' => '小4', 'school_id' => $schoolIds[1], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => false, 'contract_start_date' => '2024-04-01'],
            ['name' => '小林 だいき',   'name_kana' => 'こばやし だいき', 'gender' => 'male',   'birthdate' => '2016-07-22', 'grade' => '小4', 'school_id' => $schoolIds[0], 'disability_type' => '知的障がい',       'pickup_required' => true,  'contract_start_date' => '2024-07-01'],
            ['name' => '加藤 ののか',   'name_kana' => 'かとう ののか',   'gender' => 'female', 'birthdate' => '2015-12-01', 'grade' => '小5', 'school_id' => $schoolIds[2], 'disability_type' => 'ADHD',             'pickup_required' => true,  'contract_start_date' => '2024-04-01'],
            ['name' => '吉田 かいと',   'name_kana' => 'よしだ かいと',   'gender' => 'male',   'birthdate' => '2015-02-14', 'grade' => '小5', 'school_id' => $schoolIds[0], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => false, 'contract_start_date' => '2023-10-01'],
            ['name' => '山田 さくら',   'name_kana' => 'やまだ さくら',   'gender' => 'female', 'birthdate' => '2015-04-30', 'grade' => '小5', 'school_id' => $schoolIds[1], 'disability_type' => '学習障がい',       'pickup_required' => false, 'contract_start_date' => '2024-04-01'],
            ['name' => '松本 はると',   'name_kana' => 'まつもと はると', 'gender' => 'male',   'birthdate' => '2014-08-16', 'grade' => '小6', 'school_id' => $schoolIds[2], 'disability_type' => 'ADHD',             'pickup_required' => true,  'contract_start_date' => '2023-04-01'],

            // 小学生（高学年）
            ['name' => '井上 りこ',     'name_kana' => 'いのうえ りこ',   'gender' => 'female', 'birthdate' => '2014-01-09', 'grade' => '小6', 'school_id' => $schoolIds[0], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => true,  'contract_start_date' => '2023-07-01'],
            ['name' => '木村 えいた',   'name_kana' => 'きむら えいた',   'gender' => 'male',   'birthdate' => '2014-10-05', 'grade' => '小6', 'school_id' => $schoolIds[1], 'disability_type' => '知的障がい',       'pickup_required' => true,  'contract_start_date' => '2023-04-01'],
            ['name' => '林 つばさ',     'name_kana' => 'はやし つばさ',   'gender' => 'male',   'birthdate' => '2014-06-27', 'grade' => '小6', 'school_id' => $schoolIds[2], 'disability_type' => '発達障がい',       'pickup_required' => false, 'contract_start_date' => '2024-01-01'],

            // 特別支援学校
            ['name' => '清水 ゆうと',   'name_kana' => 'しみず ゆうと',   'gender' => 'male',   'birthdate' => '2016-05-11', 'grade' => '小4', 'school_id' => $schoolIds[4], 'disability_type' => '知的障がい（中度）', 'pickup_required' => true, 'contract_start_date' => '2024-04-01'],
            ['name' => '山口 ふうか',   'name_kana' => 'やまぐち ふうか', 'gender' => 'female', 'birthdate' => '2015-09-03', 'grade' => '小5', 'school_id' => $schoolIds[4], 'disability_type' => '自閉スペクトラム症（重度）', 'pickup_required' => true, 'contract_start_date' => '2023-04-01'],
            ['name' => '松田 れん',     'name_kana' => 'まつだ れん',     'gender' => 'male',   'birthdate' => '2014-03-19', 'grade' => '小6', 'school_id' => $schoolIds[4], 'disability_type' => '知的障がい（重度）', 'pickup_required' => true, 'contract_start_date' => '2022-10-01'],
            ['name' => '藤原 ここな',   'name_kana' => 'ふじわら ここな', 'gender' => 'female', 'birthdate' => '2017-11-28', 'grade' => '小3', 'school_id' => $schoolIds[4], 'disability_type' => 'ダウン症',         'pickup_required' => true, 'contract_start_date' => '2025-04-01'],

            // 中学生
            ['name' => '岡田 ともき',   'name_kana' => 'おかだ ともき',   'gender' => 'male',   'birthdate' => '2013-04-02', 'grade' => '中1', 'school_id' => $schoolIds[3], 'disability_type' => 'ADHD',             'pickup_required' => false, 'contract_start_date' => '2025-04-01'],
            ['name' => '高橋 あかり',   'name_kana' => 'たかはし あかり', 'gender' => 'female', 'birthdate' => '2013-07-14', 'grade' => '中1', 'school_id' => $schoolIds[3], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => false, 'contract_start_date' => '2025-04-01'],
            ['name' => '村田 しょう',   'name_kana' => 'むらた しょう',   'gender' => 'male',   'birthdate' => '2012-02-22', 'grade' => '中2', 'school_id' => $schoolIds[3], 'disability_type' => '学習障がい',       'pickup_required' => false, 'contract_start_date' => '2024-04-01'],
            ['name' => '石田 まりな',   'name_kana' => 'いしだ まりな',   'gender' => 'female', 'birthdate' => '2012-10-08', 'grade' => '中2', 'school_id' => $schoolIds[3], 'disability_type' => 'ADHD',             'pickup_required' => false, 'contract_start_date' => '2024-04-01'],
            ['name' => '河野 りょうた', 'name_kana' => 'こうの りょうた', 'gender' => 'male',   'birthdate' => '2011-05-17', 'grade' => '中3', 'school_id' => $schoolIds[3], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => true,  'contract_start_date' => '2023-04-01'],

            // 契約中断・終了のサンプル
            ['name' => '西村 はな',     'name_kana' => 'にしむら はな',   'gender' => 'female', 'birthdate' => '2016-08-25', 'grade' => '小4', 'school_id' => $schoolIds[0], 'disability_type' => '発達障がい', 'pickup_required' => false, 'contract_start_date' => '2023-04-01', 'contract_end_date' => '2025-03-31', 'contract_status' => 'ended'],
            ['name' => '森 かなた',     'name_kana' => 'もり かなた',     'gender' => 'male',   'birthdate' => '2017-03-12', 'grade' => '小3', 'school_id' => $schoolIds[1], 'disability_type' => 'ADHD',      'pickup_required' => true,  'contract_start_date' => '2024-04-01', 'contract_status' => 'suspended'],

            // 追加 activeの児童（合計30名にする）
            ['name' => '橋本 みな',     'name_kana' => 'はしもと みな',   'gender' => 'female', 'birthdate' => '2018-05-06', 'grade' => '小2', 'school_id' => $schoolIds[0], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => true,  'contract_start_date' => '2025-04-01'],
            ['name' => '辻 こころ',     'name_kana' => 'つじ こころ',     'gender' => 'female', 'birthdate' => '2019-09-01', 'grade' => '小1', 'school_id' => $schoolIds[2], 'disability_type' => '知的障がい',       'pickup_required' => false, 'contract_start_date' => '2025-09-01'],
            ['name' => '上田 けんと',   'name_kana' => 'うえだ けんと',   'gender' => 'male',   'birthdate' => '2016-12-30', 'grade' => '小4', 'school_id' => $schoolIds[4], 'disability_type' => 'ダウン症',         'pickup_required' => true,  'contract_start_date' => '2024-10-01'],
            ['name' => '池田 ゆうな',   'name_kana' => 'いけだ ゆうな',   'gender' => 'female', 'birthdate' => '2015-07-19', 'grade' => '小5', 'school_id' => $schoolIds[1], 'disability_type' => '学習障がい',       'pickup_required' => false, 'contract_start_date' => '2024-04-01'],
            ['name' => '中島 たいち',   'name_kana' => 'なかじま たいち', 'gender' => 'male',   'birthdate' => '2013-11-04', 'grade' => '中1', 'school_id' => $schoolIds[3], 'disability_type' => '自閉スペクトラム症', 'pickup_required' => false, 'contract_start_date' => '2025-04-01'],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            $row = [
                'facility_id'         => $facilityId,
                'school_id'           => $child['school_id'],
                'name'                => $child['name'],
                'name_kana'           => $child['name_kana'],
                'gender'              => $child['gender'],
                'birthdate'           => $child['birthdate'],
                'grade'               => $child['grade'],
                'disability_type'     => $child['disability_type'],
                'pickup_required'     => $child['pickup_required'],
                'contract_start_date' => $child['contract_start_date'],
                'contract_end_date'   => $child['contract_end_date'] ?? null,
                'contract_status'     => $child['contract_status'] ?? 'active',
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            DB::table('children')->insert($row);
            $inserted++;
        }

        $this->command->info("児童データを {$inserted} 名登録しました。");
        $this->command->info('学校データ ' . count($schools) . ' 校も登録しました（未登録分のみ）。');
    }
}
