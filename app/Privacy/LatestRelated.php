<?php
namespace App\Privacy;
/** Select the same per-parent MAX(date), then MAX(id), without aggregating ciphertext. */
final class LatestRelated
{
    public static function ids(string $model,string $date,string $foreign='child_id'): array {
        return $model::query()->whereNotNull($date)->orderByDesc($date)->orderByDesc('id')
            ->get(['id',$foreign])->unique($foreign)->pluck('id')->all();
    }
}
