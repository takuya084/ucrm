<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgramSeeder extends Seeder
{
    /**
     * 療育プログラムサンプルデータ
     * php artisan db:seed --class=ProgramSeeder
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $facilityId = DB::table('facilities')->value('id');
        if (!$facilityId) {
            $this->command->error('事業所が登録されていません。先に InitialDataSeeder を実行してください。');
            return;
        }

        $programs = [
            // ─── 運動 ──────────────────────────────────────────
            ['category' => 'physical', 'name' => 'トランポリン',         'duration_minutes' => 20, 'description' => 'バランス感覚・固有覚の統合を促す。楽しみながら体幹を鍛える。'],
            ['category' => 'physical', 'name' => 'バランスディスク',     'duration_minutes' => 15, 'description' => '体幹強化・姿勢保持の向上を目的とする。'],
            ['category' => 'physical', 'name' => '体操（サーキット）',   'duration_minutes' => 30, 'description' => '運動器具を使ったサーキットトレーニング。協調運動・模倣動作の練習。'],
            ['category' => 'physical', 'name' => 'ボール運動',           'duration_minutes' => 20, 'description' => 'キャッチボール・蹴りなど、目と手の協調性を高める。'],
            ['category' => 'physical', 'name' => 'リズム体操',           'duration_minutes' => 20, 'description' => '音楽に合わせた体操。リズム感・模倣能力の向上。'],
            ['category' => 'physical', 'name' => 'ストレッチ',           'duration_minutes' => 15, 'description' => '活動前後の準備・整理運動。身体感覚への気づきを促す。'],

            // ─── 認知・学習 ────────────────────────────────────
            ['category' => 'cognitive', 'name' => '宿題支援',            'duration_minutes' => 30, 'description' => '学校の宿題を支援スタッフと一緒に取り組む。集中力・学習習慣の定着。'],
            ['category' => 'cognitive', 'name' => 'パズル・ブロック',    'duration_minutes' => 20, 'description' => '空間認知・問題解決能力を高める。集中力の向上にも有効。'],
            ['category' => 'cognitive', 'name' => 'ワーキングメモリ課題', 'duration_minutes' => 20, 'description' => '短期記憶・注意力を鍛える課題。カード・アプリなどを活用。'],
            ['category' => 'cognitive', 'name' => '読み書き練習',        'duration_minutes' => 25, 'description' => '文字の読み書きを丁寧に支援。LDのある児童に個別対応。'],
            ['category' => 'cognitive', 'name' => '算数ドリル',          'duration_minutes' => 20, 'description' => '計算・数の概念を個別課題で支援する。'],
            ['category' => 'cognitive', 'name' => 'タブレット学習',      'duration_minutes' => 20, 'description' => '教育アプリを使った個別学習。視覚的なフィードバックで意欲向上。'],

            // ─── 社会性・SST ───────────────────────────────────
            ['category' => 'social', 'name' => 'SST（ソーシャルスキルトレーニング）', 'duration_minutes' => 30, 'description' => '場面設定によるロールプレイ。適切なコミュニケーション方法を練習。'],
            ['category' => 'social', 'name' => 'グループゲーム',         'duration_minutes' => 30, 'description' => 'ルールのあるゲームを通じて順番待ち・協力・感情調整を学ぶ。'],
            ['category' => 'social', 'name' => 'お楽しみ会',             'duration_minutes' => 45, 'description' => '季節のイベントや行事を通じた集団活動。自然なかかわりを促す。'],
            ['category' => 'social', 'name' => 'ロールプレイ',           'duration_minutes' => 20, 'description' => '日常場面を想定したロールプレイ。断り方・頼み方などを練習。'],
            ['category' => 'social', 'name' => '感情カード学習',         'duration_minutes' => 15, 'description' => '感情を言語化する練習。自分・他者の気持ちへの気づきを高める。'],

            // ─── 生活スキル ────────────────────────────────────
            ['category' => 'life_skills', 'name' => '着替え練習',        'duration_minutes' => 15, 'description' => 'ボタン・チャックなど細かい動作の練習。自立度の向上。'],
            ['category' => 'life_skills', 'name' => '調理活動',          'duration_minutes' => 45, 'description' => '簡単な調理を通じて、段取り・衛生・協力を学ぶ。'],
            ['category' => 'life_skills', 'name' => '掃除・片付け',      'duration_minutes' => 15, 'description' => '使ったものを元に戻す・掃除する習慣づけ。手順の理解。'],
            ['category' => 'life_skills', 'name' => '金銭管理練習',      'duration_minutes' => 20, 'description' => 'お金の種類・数え方・買い物練習。将来的な自立を見据えた支援。'],
            ['category' => 'life_skills', 'name' => '身だしなみ確認',    'duration_minutes' => 10, 'description' => '鏡を使って服装・頭髪などを自分で確認する習慣づけ。'],

            // ─── その他 ────────────────────────────────────────
            ['category' => 'other', 'name' => '創作活動（工作）',        'duration_minutes' => 40, 'description' => '手先の巧緻性・集中力・達成感を育む。季節に合わせたテーマで実施。'],
            ['category' => 'other', 'name' => '音楽活動',                'duration_minutes' => 30, 'description' => '楽器演奏・歌唱・リズム打ち。情緒の安定・自己表現を促す。'],
            ['category' => 'other', 'name' => '絵画・お絵かき',          'duration_minutes' => 30, 'description' => '自由画・課題画を通じた自己表現。集中力の向上にも有効。'],
            ['category' => 'other', 'name' => '読み聞かせ',              'duration_minutes' => 15, 'description' => '絵本の読み聞かせ。語彙・想像力・傾聴姿勢を育む。'],
            ['category' => 'other', 'name' => '自由遊び',                'duration_minutes' => 30, 'description' => '子どもの興味に合わせた自由な遊び時間。主体性・リラックスを促す。'],
        ];

        $inserted = 0;
        foreach ($programs as $program) {
            // 同名プログラムが既にあればスキップ
            $exists = DB::table('programs')
                ->where('facility_id', $facilityId)
                ->where('name', $program['name'])
                ->exists();

            if (!$exists) {
                DB::table('programs')->insert([
                    'facility_id'      => $facilityId,
                    'name'             => $program['name'],
                    'category'         => $program['category'],
                    'description'      => $program['description'],
                    'duration_minutes' => $program['duration_minutes'],
                    'is_active'        => 1,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                $inserted++;
            }
        }

        $this->command->info("プログラムデータを {$inserted} 件登録しました。");
    }
}
