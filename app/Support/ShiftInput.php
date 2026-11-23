<?php

namespace App\Support;

use App\Models\ShiftLabel;
use Illuminate\Validation\Rule;

final class ShiftInput
{
    public static function times(): array
    {
        $times = [];
        for ($hour = 6; $hour <= 22; $hour++) {
            foreach ([0, 15, 30, 45] as $minute) $times[] = sprintf('%02d:%02d', $hour, $minute);
        }
        return $times;
    }

    public static function rejectOffTimes($validator, mixed $rows, string $prefix, int $facilityId): void
    {
        if (!is_array($rows)) return;
        $off = ShiftLabel::where('facility_id', $facilityId)->where('is_off', true)->pluck('name')->all();
        foreach ($rows as $i => $row) {
            if (is_array($row) && in_array($row['work_type'] ?? '', $off, true) && !empty($row['start_time'])) {
                $validator->errors()->add($prefix.'.'.$i.'.start_time', '休み系の勤務区分には開始時刻を登録できません。');
            }
        }
    }

    public static function labelRule(int $facilityId): mixed
    {
        return Rule::in(ShiftLabel::where('facility_id', $facilityId)->pluck('name')->all());
    }
}
