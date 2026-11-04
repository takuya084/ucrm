<?php

namespace Database\Seeders;

use App\Models\AreaCategory;
use Illuminate\Database\Seeder;

/**
 * 地域区分マスタ初期データ
 *
 * 級地コード・名称の枠だけを登録する。単価（unit_price）はこども家庭庁告示の
 * 正式な数値を確認できていないため null のまま投入する（捏造禁止）。
 * 実際の単価は「請求設定 > 地域区分マスタ」画面で、告示を確認した上で入力すること。
 *
 * php artisan db:seed --class=AreaCategorySeeder
 */
class AreaCategorySeeder extends Seeder
{
    public function run(): void
    {
        $numerals = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十',
                     '十一', '十二', '十三', '十四', '十五', '十六', '十七', '十八', '十九'];

        $inserted = 0;
        foreach ($numerals as $i => $numeral) {
            $code = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            if (AreaCategory::where('code', $code)->exists()) continue;

            AreaCategory::create([
                'code'       => $code,
                'label'      => "{$numeral}級地",
                'unit_price' => null,
                'valid_from' => '2024-04-01',
            ]);
            $inserted++;
        }

        if (!AreaCategory::where('code', '20')->exists()) {
            AreaCategory::create([
                'code'       => '20',
                'label'      => 'その他',
                'unit_price' => null,
                'valid_from' => '2024-04-01',
            ]);
            $inserted++;
        }

        $this->command->info("地域区分マスタを {$inserted} 件登録しました（単価は未確認のため空欄）。");
    }
}
