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

    public static function labelRule(int $facilityId): mixed
    {
        return Rule::in(ShiftLabel::where('facility_id', $facilityId)->pluck('name')->all());
    }
}
