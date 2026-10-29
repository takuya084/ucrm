<?php

namespace App\Services;

use App\Models\{Child, Facility};
use Illuminate\Validation\ValidationException;

class YoyakuLinkGuard
{
    public static function lockFacility(int $id): Facility
    {
        return Facility::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public static function assertAvailable(int $facilityId, ?int $childId, ?int $userId): void
    {
        if ($userId === null) return;
        $matches = Child::where('facility_id', $facilityId)->where('yoyaku_user_id', $userId)
            ->when($childId !== null, fn ($q) => $q->where('id', '!=', $childId))
            ->lockForUpdate()->get(['id']);
        if ($matches->isNotEmpty()) {
            throw ValidationException::withMessages(['yoyaku_user_id' => 'この予約アカウントは、すでに同じ事業所の別の児童に紐付いています。']);
        }
    }

    public static function assertConfiguration(Facility $current, Facility $expected): void
    {
        if ((string) $current->yoyaku_business_id !== (string) $expected->yoyaku_business_id
            || $current->yoyaku_api_token !== $expected->yoyaku_api_token
            || !$current->yoyaku_business_id
            || Facility::where('yoyaku_business_id', $current->yoyaku_business_id)->count() !== 1) {
            throw ValidationException::withMessages(['yoyaku_user_id' => '事業所の連携設定が変更されたか重複しています。設定を確認して再実行してください。']);
        }
    }
}
