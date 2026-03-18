<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ダッシュボードのアラート表示確認用ダミーデータ
 * php artisan db:seed --class=DashboardAlertSeeder
 */
class DashboardAlertSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // 事業所ID取得
        $facilityId = DB::table('facilities')->value('id');

        // スタッフID取得
        $staffId = DB::table('staff')->where('facility_id', $facilityId)->value('id');

        // 対象児童（最大5名）取得
        $children = DB::table('children')
            ->where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->limit(5)
            ->get(['id', 'name']);

        if ($children->isEmpty()) {
            $this->command->error('対象の児童が見つかりません。先に InitialDataSeeder と ChildScheduleSeeder を実行してください。');
            return;
        }

        $childIds = $children->pluck('id');

        // ─── モニタリング記録（next_review_date がアラート範囲に入るよう設定）─────
        // 既存を削除して入れ直し
        DB::table('monitoring_records')->whereIn('child_id', $childIds)->delete();

        $monitoringData = [
            [
                'days_offset'    => -5,   // 5日超過（赤）
                'label'          => '5日超過',
            ],
            [
                'days_offset'    => 0,    // 本日期限（赤）
                'label'          => '本日期限',
            ],
            [
                'days_offset'    => 7,    // 7日以内（オレンジ）
                'label'          => '7日後',
            ],
            [
                'days_offset'    => 20,   // 20日後（黄色）
                'label'          => '20日後',
            ],
        ];

        foreach ($children->take(count($monitoringData)) as $i => $child) {
            $offset         = $monitoringData[$i]['days_offset'];
            $monitoringDate = date('Y-m-d', strtotime('-6 months'));
            $nextReview     = date('Y-m-d', strtotime($today . " +{$offset} days"));

            DB::table('monitoring_records')->insert([
                'child_id'         => $child->id,
                'staff_id'         => $staffId,
                'monitoring_date'  => $monitoringDate,
                'period_from'      => date('Y-m-d', strtotime('-6 months')),
                'period_to'        => date('Y-m-d', strtotime('-1 day')),
                'support_summary'  => "{$child->name} のモニタリング記録（テストデータ）",
                'strengths'        => '集団活動への参加意欲が向上した。',
                'challenges'       => '切り替えに時間がかかることがある。',
                'next_review_date' => $nextReview,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $this->command->line("  モニタリング追加: {$child->name} → 次回 {$nextReview}（{$monitoringData[$i]['label']}）");
        }

        // ─── 個別支援計画（guardian_agreement = false で同意待ち）─────────────
        DB::table('support_plans')->whereIn('child_id', $childIds)->delete();

        $planPatterns = [
            ['valid_from' => date('Y-m-d', strtotime('-3 months')), 'valid_to' => date('Y-m-d', strtotime('+3 months'))],
            ['valid_from' => date('Y-m-d', strtotime('-1 month')),  'valid_to' => date('Y-m-d', strtotime('+5 months'))],
            ['valid_from' => $today,                                  'valid_to' => date('Y-m-d', strtotime('+6 months'))],
        ];

        foreach ($children->take(count($planPatterns)) as $i => $child) {
            $pattern = $planPatterns[$i];

            DB::table('support_plans')->insert([
                'child_id'          => $child->id,
                'staff_id'          => $staffId,
                'plan_date'         => date('Y-m-d', strtotime($pattern['valid_from'] . ' -3 days')),
                'valid_from'        => $pattern['valid_from'],
                'valid_to'          => $pattern['valid_to'],
                'long_term_goal'    => '生活の質の向上と社会参加の促進',
                'short_term_goal'   => '集団活動への参加とコミュニケーション力の向上',
                'support_policy'    => '個別支援を通じて自己肯定感を育む',
                'program_content'   => '運動療育・SST・生活スキルトレーニング',
                'guardian_agreement' => false,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            $this->command->line("  個別支援計画追加: {$child->name} → {$pattern['valid_from']} 〜 {$pattern['valid_to']}（同意待ち）");
        }

        $this->command->info('ダッシュボードアラート用ダミーデータを作成しました。');
    }
}
