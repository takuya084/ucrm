<?php

namespace Database\Seeders;

use App\Models\ServiceCodeMaster;
use Illuminate\Database\Seeder;

/**
 * サービスコードマスタシーダー
 *
 * 令和6年4月報酬改定対応
 * ※単位数は概算値。実運用時には厚労省告示の正式な単位数に更新してください。
 */
class ServiceCodeMasterSeeder extends Seeder
{
    public function run()
    {
        $revisionDate = '2024-04-01';
        $validFrom    = '2024-04-01';

        $codes = [
            // ──────────────────────────────────────────────
            // 放課後等デイサービス（サービス種別: houday / コード63）
            // ──────────────────────────────────────────────

            // 基本報酬 - 授業終了後（学校日）
            [
                'service_type' => 'houday', 'service_code' => '631000',
                'service_name' => '放デイ1・授業終了後（定員10人以下）',
                'unit_count' => 604, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'school_day', 'max_capacity' => 10],
            ],
            [
                'service_type' => 'houday', 'service_code' => '631001',
                'service_name' => '放デイ1・授業終了後（定員11〜20人）',
                'unit_count' => 570, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'school_day', 'min_capacity' => 11, 'max_capacity' => 20],
            ],
            [
                'service_type' => 'houday', 'service_code' => '631002',
                'service_name' => '放デイ1・授業終了後（定員21人以上）',
                'unit_count' => 536, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'school_day', 'min_capacity' => 21],
            ],

            // 基本報酬 - 休業日
            [
                'service_type' => 'houday', 'service_code' => '632000',
                'service_name' => '放デイ1・休業日（定員10人以下）',
                'unit_count' => 721, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'holiday', 'max_capacity' => 10],
            ],
            [
                'service_type' => 'houday', 'service_code' => '632001',
                'service_name' => '放デイ1・休業日（定員11〜20人）',
                'unit_count' => 679, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'holiday', 'min_capacity' => 11, 'max_capacity' => 20],
            ],
            [
                'service_type' => 'houday', 'service_code' => '632002',
                'service_name' => '放デイ1・休業日（定員21人以上）',
                'unit_count' => 639, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'holiday', 'min_capacity' => 21],
            ],

            // 加算 - 放課後等デイサービス
            [
                'service_type' => 'houday', 'service_code' => '636100',
                'service_name' => '送迎加算（片道）',
                'unit_count' => 54, 'unit_type' => 'per_time', 'category' => 'addition',
                'conditions' => ['requires_pickup' => true],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636101',
                'service_name' => '送迎加算（片道・送り）',
                'unit_count' => 54, 'unit_type' => 'per_time', 'category' => 'addition',
                'conditions' => ['requires_dropoff' => true],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636200',
                'service_name' => '延長支援加算（1時間未満）',
                'unit_count' => 61, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['extension_after' => '18:00'],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636201',
                'service_name' => '延長支援加算（1時間以上2時間未満）',
                'unit_count' => 92, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['extension_after' => '19:00'],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636300',
                'service_name' => '欠席時対応加算',
                'unit_count' => 94, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['absent_with_notice' => true],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636400',
                'service_name' => '児童指導員等加配加算（理学療法士等）',
                'unit_count' => 187, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['requires_qualification' => 'pt_ot_st'],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636401',
                'service_name' => '児童指導員等加配加算（児童指導員等）',
                'unit_count' => 123, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['requires_qualification' => 'child_welfare'],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636500',
                'service_name' => '専門的支援加算',
                'unit_count' => 187, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['requires_qualification' => 'specialist'],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636600',
                'service_name' => '家庭連携加算',
                'unit_count' => 187, 'unit_type' => 'per_month', 'category' => 'addition',
                'conditions' => ['home_visit' => true],
            ],
            [
                'service_type' => 'houday', 'service_code' => '636700',
                'service_name' => '事業所内相談支援加算',
                'unit_count' => 100, 'unit_type' => 'per_month', 'category' => 'addition',
                'conditions' => ['in_facility_consultation' => true],
            ],

            // 減算 - 放課後等デイサービス
            [
                'service_type' => 'houday', 'service_code' => '637100',
                'service_name' => '定員超過利用減算',
                'unit_count' => -30, 'unit_type' => 'per_day', 'category' => 'subtraction',
                'conditions' => ['capacity_exceeded' => true],
            ],
            [
                'service_type' => 'houday', 'service_code' => '637200',
                'service_name' => 'サービス提供職員欠如減算',
                'unit_count' => -30, 'unit_type' => 'per_day', 'category' => 'subtraction',
                'conditions' => ['staff_shortage' => true],
            ],

            // ──────────────────────────────────────────────
            // 児童発達支援（サービス種別: jidou / コード61）
            // ──────────────────────────────────────────────

            // 基本報酬 - 児童発達支援
            [
                'service_type' => 'jidou', 'service_code' => '611000',
                'service_name' => '児発1（定員10人以下）',
                'unit_count' => 827, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'school_day', 'max_capacity' => 10],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '611001',
                'service_name' => '児発1（定員11〜20人）',
                'unit_count' => 776, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'school_day', 'min_capacity' => 11, 'max_capacity' => 20],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '611002',
                'service_name' => '児発1（定員21人以上）',
                'unit_count' => 729, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'school_day', 'min_capacity' => 21],
            ],

            // 基本報酬 - 児発 休業日（未就学児のため実質同じだが区分として）
            [
                'service_type' => 'jidou', 'service_code' => '612000',
                'service_name' => '児発1・休業日（定員10人以下）',
                'unit_count' => 827, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'holiday', 'max_capacity' => 10],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '612001',
                'service_name' => '児発1・休業日（定員11〜20人）',
                'unit_count' => 776, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'holiday', 'min_capacity' => 11, 'max_capacity' => 20],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '612002',
                'service_name' => '児発1・休業日（定員21人以上）',
                'unit_count' => 729, 'unit_type' => 'per_day', 'category' => 'base',
                'conditions' => ['day_type' => 'holiday', 'min_capacity' => 21],
            ],

            // 加算 - 児童発達支援
            [
                'service_type' => 'jidou', 'service_code' => '616100',
                'service_name' => '送迎加算（片道）',
                'unit_count' => 54, 'unit_type' => 'per_time', 'category' => 'addition',
                'conditions' => ['requires_pickup' => true],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616101',
                'service_name' => '送迎加算（片道・送り）',
                'unit_count' => 54, 'unit_type' => 'per_time', 'category' => 'addition',
                'conditions' => ['requires_dropoff' => true],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616200',
                'service_name' => '延長支援加算（1時間未満）',
                'unit_count' => 61, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['extension_after' => '18:00'],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616300',
                'service_name' => '欠席時対応加算',
                'unit_count' => 94, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['absent_with_notice' => true],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616400',
                'service_name' => '児童指導員等加配加算（理学療法士等）',
                'unit_count' => 187, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['requires_qualification' => 'pt_ot_st'],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616401',
                'service_name' => '児童指導員等加配加算（児童指導員等）',
                'unit_count' => 123, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['requires_qualification' => 'child_welfare'],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616500',
                'service_name' => '専門的支援加算',
                'unit_count' => 187, 'unit_type' => 'per_day', 'category' => 'addition',
                'conditions' => ['requires_qualification' => 'specialist'],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616600',
                'service_name' => '家庭連携加算',
                'unit_count' => 187, 'unit_type' => 'per_month', 'category' => 'addition',
                'conditions' => ['home_visit' => true],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '616700',
                'service_name' => '関係機関連携加算',
                'unit_count' => 200, 'unit_type' => 'per_month', 'category' => 'addition',
                'conditions' => ['agency_cooperation' => true],
            ],

            // 減算 - 児童発達支援
            [
                'service_type' => 'jidou', 'service_code' => '617100',
                'service_name' => '定員超過利用減算',
                'unit_count' => -30, 'unit_type' => 'per_day', 'category' => 'subtraction',
                'conditions' => ['capacity_exceeded' => true],
            ],
            [
                'service_type' => 'jidou', 'service_code' => '617200',
                'service_name' => 'サービス提供職員欠如減算',
                'unit_count' => -30, 'unit_type' => 'per_day', 'category' => 'subtraction',
                'conditions' => ['staff_shortage' => true],
            ],
        ];

        foreach ($codes as $code) {
            ServiceCodeMaster::updateOrCreate(
                [
                    'service_code' => $code['service_code'],
                    'valid_from'   => $validFrom,
                ],
                [
                    'revision_date' => $revisionDate,
                    'service_type'  => $code['service_type'],
                    'service_name'  => $code['service_name'],
                    'unit_count'    => $code['unit_count'],
                    'unit_type'     => $code['unit_type'],
                    'category'      => $code['category'],
                    'conditions'    => $code['conditions'],
                    'valid_to'      => null,
                ]
            );
        }
    }
}
