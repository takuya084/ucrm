<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Services\YoyakuSyncService;
use Illuminate\Console\Command;

class ImportYoyakuActuals extends Command
{
    protected $signature = 'yoyaku:import-actuals
                            {--date= : Y-m-d 形式。省略時は前日}
                            {--facility= : 施設ID。省略時は全施設}';

    protected $description = 'p-yoyaku から送迎実績を取り込み、DailyServiceRecord の送迎フラグを更新します';

    public function handle(YoyakuSyncService $sync): int
    {
        $date = $this->option('date') ?: now()->subDay()->format('Y-m-d');

        $query = Facility::whereNotNull('yoyaku_business_id');
        if ($fid = $this->option('facility')) {
            $query->where('id', $fid);
        }

        $facilities = $query->get(['id', 'name']);
        if ($facilities->isEmpty()) {
            $this->warn('対象の施設がありません（yoyaku_business_id 未設定）。');
            return self::SUCCESS;
        }

        foreach ($facilities as $f) {
            $r = $sync->importActuals($f->id, $date);
            $this->line("[{$f->name}] {$date}  取り込み: {$r['imported']} / スキップ: {$r['skipped']}");
        }

        return self::SUCCESS;
    }
}
