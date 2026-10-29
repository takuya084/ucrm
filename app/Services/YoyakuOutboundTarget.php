<?php

namespace App\Services;

use App\Models\Child;
use App\Models\Facility;

/** Non-secret destination snapshot persisted with an outbound queue job. */
class YoyakuOutboundTarget
{
    public static function capture(int $facilityId, int $childId, string $date): array
    {
        $facility = Facility::find($facilityId);
        $child = Child::where('facility_id', $facilityId)->find($childId);

        return [
            'facility_id' => $facilityId,
            'child_id' => $childId,
            'date' => $date,
            'business_id' => $facility?->yoyaku_business_id ? (int) $facility->yoyaku_business_id : null,
            'user_id' => $child?->yoyaku_user_id ? (int) $child->yoyaku_user_id : null,
            'token_hash' => hash('sha256', (string) $facility?->yoyaku_api_token),
        ];
    }

    /** False means unlinked; changed or ambiguous targets fail visibly. */
    public static function assertCurrent(?array $expected, int $facilityId, int $childId, string $date): bool
    {
        if ($expected === null) {
            throw new \RuntimeException('Outbound integration target is unavailable; enqueue a new job');
        }
        $current = self::capture($facilityId, $childId, $date);
        if ($current !== $expected) {
            throw new \RuntimeException('Outbound integration target changed; enqueue a new job');
        }
        if (!$current['business_id'] || !$current['user_id']) {
            return false;
        }
        if (Facility::where('yoyaku_business_id', $current['business_id'])->count() !== 1
            || Child::where('facility_id', $facilityId)->where('yoyaku_user_id', $current['user_id'])->count() !== 1) {
            throw new \RuntimeException('Ambiguous outbound integration target');
        }
        return true;
    }
}
