<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffWorkPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patterns'                => ['required', 'array', 'size:7'],
            'patterns.*.day_of_week'  => ['required', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'patterns.*.start_time'   => ['nullable', 'date_format:H:i'],
            'patterns.*.work_type'    => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patterns.*.day_of_week' => '曜日',
            'patterns.*.start_time'  => '開始時刻',
            'patterns.*.work_type'   => '勤務区分',
        ];
    }
}
