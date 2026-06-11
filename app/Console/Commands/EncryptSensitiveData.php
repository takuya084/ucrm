<?php

namespace App\Console\Commands;

use App\Models\Child;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptSensitiveData extends Command
{
    protected $signature = 'app:encrypt-sensitive-data {--dry-run : 件数の確認のみ行う}';

    protected $description = '児童の要配慮個人情報（障がい・アレルギー・配慮事項）の既存平文データを暗号化する';

    /** 暗号化対象のカラム（Child の EncryptedOrPlainText キャストと一致させること） */
    private const COLUMNS = ['disability_note', 'allergy_note', 'care_note'];

    public function handle(): int
    {
        $encrypted = 0;
        $skipped   = 0;

        Child::withTrashed()->chunkById(100, function ($children) use (&$encrypted, &$skipped) {
            foreach ($children as $child) {
                $updates = [];

                foreach (self::COLUMNS as $column) {
                    $raw = $child->getRawOriginal($column);
                    if ($raw === null || $raw === '') {
                        continue;
                    }

                    if ($this->isEncrypted($raw)) {
                        $skipped++;
                        continue;
                    }

                    $updates[$column] = Crypt::encryptString($raw);
                }

                if ($updates) {
                    if (!$this->option('dry-run')) {
                        // モデルのミューテタを通すと二重暗号化になるため直接更新
                        DB::table('children')->where('id', $child->id)->update($updates);
                    }
                    $encrypted += count($updates);
                }
            }
        });

        $mode = $this->option('dry-run') ? '[dry-run] ' : '';
        $this->info("{$mode}暗号化: {$encrypted}件 / 暗号化済みスキップ: {$skipped}件");

        return self::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
