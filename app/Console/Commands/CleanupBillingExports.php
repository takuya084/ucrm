<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupBillingExports extends Command
{
    protected $signature = 'billing:cleanup-exports';

    protected $description = '保持期限（config: billing.export_retention_days）を過ぎた出力ファイル（請求CSV/PDF/ZIP・連絡帳PDF）を削除する';

    /** 出力ファイルが置かれるディレクトリ（個人情報を含むため保持期限で削除する） */
    private const TARGET_DIRECTORIES = [
        'billing_csv',
        'billing_exports',
        'performance-records',
        'cap-management',
        'proxy-receipts',
        'receipts',
        'contact-notes',
    ];

    public function handle(): int
    {
        $retentionDays = (int) config('billing.export_retention_days', 90);
        $threshold     = now()->subDays($retentionDays)->getTimestamp();
        $disk          = Storage::disk('local');
        $deleted       = 0;

        foreach (self::TARGET_DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $file) {
                if ($disk->lastModified($file) < $threshold) {
                    $disk->delete($file);
                    $deleted++;
                }
            }
        }

        $this->info("保持期限 {$retentionDays} 日を過ぎた出力ファイルを {$deleted} 件削除しました。");

        return self::SUCCESS;
    }
}
