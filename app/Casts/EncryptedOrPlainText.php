<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * 保存時に暗号化し、読み出し時に復号するキャスト。
 *
 * Laravel 標準の encrypted キャストと異なり、復号に失敗した場合は
 * 平文データとしてそのまま返す（暗号化導入前の既存データの移行期間用）。
 * 既存データの一括暗号化は app:encrypt-sensitive-data コマンドで行う。
 */
class EncryptedOrPlainText implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }
}
