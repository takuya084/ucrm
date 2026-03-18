<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InquirySeeder extends Seeder
{
    /**
     * 問い合わせサンプルデータ
     * php artisan db:seed --class=InquirySeeder
     */
    public function run(): void
    {
        $facilityId = DB::table('facilities')->value('id');
        if (!$facilityId) {
            $this->command->error('事業所が登録されていません。');
            return;
        }

        // 児童とスタッフを取得
        $children = DB::table('children')
            ->where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->pluck('id')
            ->toArray();

        $staffIds = DB::table('staff')
            ->where('facility_id', $facilityId)
            ->pluck('id')
            ->toArray();

        if (empty($children) || empty($staffIds)) {
            $this->command->error('児童またはスタッフが登録されていません。先に ChildrenSeeder / InitialDataSeeder を実行してください。');
            return;
        }

        $inquiries = [
            // 未対応
            [
                'child_index'    => 0,
                'contact_method' => 'tel',
                'contacted_at'   => '2026-03-17 09:15:00',
                'category'       => 'schedule',
                'subject'        => '今週の利用日変更の相談',
                'content'        => '木曜日に病院の予約が入ってしまいました。水曜日に変更できますか？',
                'response'       => null,
                'status'         => 'open',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 1,
                'contact_method' => 'line',
                'contacted_at'   => '2026-03-16 18:30:00',
                'category'       => 'support',
                'subject'        => '最近の様子について確認したい',
                'content'        => '家で癇癪が増えていて心配しています。施設でも同様の様子がありますか？何か原因に心当たりはありますか？',
                'response'       => null,
                'status'         => 'open',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 2,
                'contact_method' => 'tel',
                'contacted_at'   => '2026-03-15 10:00:00',
                'category'       => 'billing',
                'subject'        => '2月分の請求書について',
                'content'        => '2月分の請求書がまだ届いていません。いつ頃送ってもらえますか？',
                'response'       => null,
                'status'         => 'open',
                'is_escalated'   => false,
            ],
            // 対応中
            [
                'child_index'    => 3,
                'contact_method' => 'email',
                'contacted_at'   => '2026-03-14 14:20:00',
                'category'       => 'support',
                'subject'        => '個別支援計画の内容確認',
                'content'        => '今年度の個別支援計画について、面談の日程を調整したいです。来週以降でお願いできますか？',
                'response'       => '3/20（木）14時でご確認いただきました。資料を事前にお送りします。',
                'status'         => 'in_progress',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 4,
                'contact_method' => 'in_person',
                'contacted_at'   => '2026-03-14 16:45:00',
                'category'       => 'schedule',
                'subject'        => '春休み期間中の利用について',
                'content'        => '春休み中は毎日利用したいのですが、受給者証の日数が足りるか確認してほしい。',
                'response'       => '受給者証を確認中。残日数を計算してご連絡します。',
                'status'         => 'in_progress',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 5,
                'contact_method' => 'tel',
                'contacted_at'   => '2026-03-13 11:00:00',
                'category'       => 'complaint',
                'subject'        => '送迎時刻が遅れることについて',
                'content'        => '先週の月曜日、送迎が30分以上遅れました。仕事の都合上、時間通りにお迎えに来てもらわないと困ります。改善をお願いしたい。',
                'response'       => '送迎ルートの見直しを検討中。管理者に報告済み。',
                'status'         => 'in_progress',
                'is_escalated'   => true,
            ],
            // 完了
            [
                'child_index'    => 6,
                'contact_method' => 'line',
                'contacted_at'   => '2026-03-10 09:30:00',
                'category'       => 'schedule',
                'subject'        => '3月の利用予定を送ってほしい',
                'content'        => '3月のカレンダーをLINEで送ってもらえますか？',
                'response'       => 'LINEにて3月の利用予定表をお送りしました。',
                'status'         => 'closed',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 7,
                'contact_method' => 'tel',
                'contacted_at'   => '2026-03-08 10:15:00',
                'category'       => 'billing',
                'subject'        => '上限管理の書類について',
                'content'        => '他施設との上限管理の書類が必要です。記入してもらえますか？',
                'response'       => '書類を作成し、3/10にお渡しました。',
                'status'         => 'closed',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 8,
                'contact_method' => 'in_person',
                'contacted_at'   => '2026-03-07 15:00:00',
                'category'       => 'support',
                'subject'        => 'トランポリン活動について',
                'content'        => '家でもトランポリンを購入しようと思っています。どのくらいの大きさがよいか教えてほしい。',
                'response'       => '室内用の小型タイプをおすすめしました。メーカー情報をお伝えしました。',
                'status'         => 'closed',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 9,
                'contact_method' => 'email',
                'contacted_at'   => '2026-03-05 13:00:00',
                'category'       => 'other',
                'subject'        => '施設見学の申し込み',
                'content'        => '来月から利用を検討している知人がいます。施設見学の日程を教えてください。',
                'response'       => '見学日程を3/12に設定し、メールにてご案内しました。',
                'status'         => 'closed',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 0,
                'contact_method' => 'tel',
                'contacted_at'   => '2026-02-28 09:00:00',
                'category'       => 'support',
                'subject'        => '学校との連携について',
                'content'        => '担任の先生から連絡帳に「最近落ち着いてきた」と書いてもらいました。施設での取り組みのおかげかもしれません。ありがとうございます。',
                'response'       => 'お礼のお言葉ありがとうございます。引き続き連携してまいります。',
                'status'         => 'closed',
                'is_escalated'   => false,
            ],
            [
                'child_index'    => 1,
                'contact_method' => 'line',
                'contacted_at'   => '2026-02-20 17:00:00',
                'category'       => 'schedule',
                'subject'        => '発熱による当日キャンセル',
                'content'        => '今日は38.5度の熱があるためお休みします。',
                'response'       => '承知しました。お大事にしてください。',
                'status'         => 'closed',
                'is_escalated'   => false,
            ],
        ];

        $inserted = 0;
        foreach ($inquiries as $i => $data) {
            $childId  = $children[$data['child_index'] % count($children)];
            $staffId  = $staffIds[$i % count($staffIds)];

            DB::table('inquiries')->insert([
                'child_id'       => $childId,
                'guardian_id'    => null,
                'staff_id'       => $staffId,
                'contact_method' => $data['contact_method'],
                'contacted_at'   => $data['contacted_at'],
                'category'       => $data['category'],
                'subject'        => $data['subject'],
                'content'        => $data['content'],
                'response'       => $data['response'],
                'status'         => $data['status'],
                'is_escalated'   => $data['is_escalated'] ? 1 : 0,
                'created_at'     => $data['contacted_at'],
                'updated_at'     => $data['contacted_at'],
            ]);
            $inserted++;
        }

        $this->command->info("問い合わせデータを {$inserted} 件登録しました。");
    }
}
