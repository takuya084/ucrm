<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 過誤申立・返戻のダミーデータ
 *
 * php artisan db:seed --class=BillingErrorReturnSeeder
 *
 * 前提: 2026-03 の確定済み請求データを自動生成し、
 *       そこに過誤申立と返戻を紐付ける
 */
class BillingErrorReturnSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now()->format('Y-m-d H:i:s');
        $facilityId = DB::table('facilities')->value('id');
        $staffId    = DB::table('staff')->where('facility_id', $facilityId)->value('id');

        if (!$facilityId) {
            $this->command->error('事業所がありません。');
            return;
        }

        $children = DB::table('children')
            ->where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->limit(6)
            ->get();

        if ($children->count() < 4) {
            $this->command->error('児童が4名以上必要です。ShiftSampleSeeder を先に実行してください。');
            return;
        }

        // ====================================================
        // 1. 2026-03 の確定済み請求期間を作成
        // ====================================================
        $pastMonth = '2026-03';

        // 既存があれば削除
        $existingPeriod = DB::table('billing_periods')
            ->where('facility_id', $facilityId)
            ->where('year_month', $pastMonth)
            ->first();

        if ($existingPeriod) {
            // 関連データをカスケード削除するため billing_details を先に取得
            $existingDetailIds = DB::table('billing_details')
                ->where('billing_period_id', $existingPeriod->id)
                ->pluck('id')->toArray();

            if ($existingDetailIds) {
                DB::table('error_claims')->whereIn('billing_detail_id', $existingDetailIds)->delete();
                DB::table('claim_returns')->whereIn('billing_detail_id', $existingDetailIds)->delete();
                DB::table('billing_detail_lines')->whereIn('billing_detail_id', $existingDetailIds)->delete();
                DB::table('billing_details')->whereIn('id', $existingDetailIds)->delete();
            }
            DB::table('billing_periods')->where('id', $existingPeriod->id)->delete();
        }

        $periodId = DB::table('billing_periods')->insertGetId([
            'facility_id'  => $facilityId,
            'year_month'   => $pastMonth,
            'status'       => 'submitted',
            'submitted_at' => '2026-04-05 10:00:00',
            'confirmed_by' => $staffId,
            'notes'        => '3月分請求（テストデータ）',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        $this->command->info("✓ 請求期間を作成（{$pastMonth}・提出済）");

        // ====================================================
        // 2. 児童6名分の確定済み請求明細を作成
        // ====================================================
        $certIds = DB::table('recipient_certificates')
            ->whereIn('child_id', $children->pluck('id'))
            ->where('status', 'active')
            ->pluck('id', 'child_id');

        $detailData = [
            // [total_days, total_units, total_amount, insurance_amount, copay, cap, cap_applied]
            ['days' => 18, 'units' => 11286, 'total' => 112860, 'insurance' => 108260, 'copay' => 11286, 'cap' => 4600,  'cap_applied' => 4600],
            ['days' => 15, 'units' =>  9420, 'total' =>  94200, 'insurance' =>  89600, 'copay' =>  9420, 'cap' => 4600,  'cap_applied' => 4600],
            ['days' => 20, 'units' => 12560, 'total' => 125600, 'insurance' =>  88400, 'copay' => 12560, 'cap' => 37200, 'cap_applied' => 12560],
            ['days' => 12, 'units' =>  7536, 'total' =>  75360, 'insurance' =>  70760, 'copay' =>  7536, 'cap' => 4600,  'cap_applied' => 4600],
            ['days' => 16, 'units' => 10048, 'total' => 100480, 'insurance' =>  90432, 'copay' => 10048, 'cap' => 37200, 'cap_applied' => 10048],
            ['days' => 10, 'units' =>  6280, 'total' =>  62800, 'insurance' =>  62800, 'copay' =>  6280, 'cap' => 0,     'cap_applied' => 0],
        ];

        $detailIds = [];
        foreach ($children->values() as $i => $child) {
            $d = $detailData[$i];
            $detailIds[$i] = DB::table('billing_details')->insertGetId([
                'billing_period_id'        => $periodId,
                'child_id'                 => $child->id,
                'recipient_certificate_id' => $certIds[$child->id] ?? null,
                'service_type'             => 'houday',
                'total_days'               => $d['days'],
                'total_units'              => $d['units'],
                'unit_price_yen'           => 10.00,
                'total_amount'             => $d['total'],
                'insurance_amount'         => $d['insurance'],
                'copayment_amount'         => $d['copay'],
                'copayment_cap'            => $d['cap'],
                'copayment_cap_applied'    => $d['cap_applied'],
                'status'                   => 'submitted',
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);
        }
        $this->command->info('✓ 請求明細を作成（' . count($detailIds) . '件）');

        // ====================================================
        // 3. 過誤申立データ（3件）
        // ====================================================
        DB::table('error_claims')->where('facility_id', $facilityId)->delete();

        $errorClaims = [
            [
                'detail_index' => 0,
                'claim_type'   => 'full_cancel',
                'reason'       => '受給者証番号の誤り。正しい番号で再請求予定。',
                'status'       => 'submitted',
                'submitted_at' => '2026-04-08 14:30:00',
            ],
            [
                'detail_index' => 1,
                'claim_type'   => 'partial_correction',
                'reason'       => '3月15日の送迎加算が未算定。迎え・送り各54単位の追加が必要。',
                'status'       => 'draft',
                'submitted_at' => null,
            ],
            [
                'detail_index' => 3,
                'claim_type'   => 'full_cancel',
                'reason'       => '他市への転出に伴う請求先変更。当事業所分を取消し、転出先市区町村へ再請求。',
                'status'       => 'accepted',
                'submitted_at' => '2026-04-06 09:00:00',
            ],
        ];

        foreach ($errorClaims as $ec) {
            $child = $children->values()[$ec['detail_index']];
            DB::table('error_claims')->insert([
                'facility_id'       => $facilityId,
                'billing_detail_id' => $detailIds[$ec['detail_index']],
                'original_year_month' => $pastMonth,
                'child_id'          => $child->id,
                'claim_type'        => $ec['claim_type'],
                'reason'            => $ec['reason'],
                'status'            => $ec['status'],
                'submitted_at'      => $ec['submitted_at'],
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
        $this->command->info('✓ 過誤申立を作成（' . count($errorClaims) . '件）');

        // ====================================================
        // 4. 返戻データ（3件）
        // ====================================================
        DB::table('claim_returns')->where('facility_id', $facilityId)->delete();

        $returns = [
            [
                'detail_index'  => 2,
                'return_code'   => 'E001',
                'return_reason' => '受給者証の有効期間外。証の更新を確認して再請求してください。',
                'original_amount' => 125600,
                'status'        => 'returned',
                'received_at'   => '2026-04-10',
            ],
            [
                'detail_index'  => 4,
                'return_code'   => 'E012',
                'return_reason' => '事業所番号不一致。正しい事業所番号を確認してください。',
                'original_amount' => 100480,
                'status'        => 'resubmitting',
                'received_at'   => '2026-04-10',
            ],
            [
                'detail_index'  => 5,
                'return_code'   => 'E005',
                'return_reason' => '支給量超過。月間利用日数が支給量を超えています。',
                'original_amount' => 62800,
                'status'        => 'resolved',
                'received_at'   => '2026-04-08',
            ],
        ];

        foreach ($returns as $ret) {
            $child = $children->values()[$ret['detail_index']];
            DB::table('claim_returns')->insert([
                'facility_id'       => $facilityId,
                'billing_detail_id' => $detailIds[$ret['detail_index']],
                'year_month'        => $pastMonth,
                'child_id'          => $child->id,
                'return_code'       => $ret['return_code'],
                'return_reason'     => $ret['return_reason'],
                'original_amount'   => $ret['original_amount'],
                'status'            => $ret['status'],
                'resubmitted_billing_detail_id' => null,
                'received_at'       => $ret['received_at'],
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
        $this->command->info('✓ 返戻データを作成（' . count($returns) . '件）');

        // ====================================================
        // サマリー
        // ====================================================
        $this->command->newLine();
        $this->command->info('=== 過誤・返戻テストデータ投入完了 ===');
        $this->command->table(
            ['種別', '件数', 'ステータス'],
            [
                ['過誤申立', '3件', '提出済1、下書き1、受理1'],
                ['返戻',     '3件', '返戻1、再請求準備中1、解決済1'],
            ]
        );
    }
}
