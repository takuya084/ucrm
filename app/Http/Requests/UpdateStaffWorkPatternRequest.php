<?php

namespace App\Http\Requests;

use App\Support\ShiftInput;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffWorkPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->user()?->staff;
        return $staff?->is_active && $staff->role === 'admin'
            && $this->route('staff')?->facility_id === $staff->facility_id;
    }

    public function rules(): array
    {
        return [
            'patterns'                => ['required', 'array', 'size:7'],
            'patterns.*.day_of_week'  => ['required', 'distinct', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'patterns.*.start_time'   => ['nullable', 'date_format:H:i', Rule::in(ShiftInput::times())],
            'patterns.*.work_type'    => ['nullable', 'string', 'max:30', ShiftInput::labelRule($this->user()->staff->facility_id)],
        ];
    }

    public function messages(): array
    {
        return [
            'patterns.*.work_type.in' => '未登録の勤務区分は保存できません。',
            'patterns.*.start_time.in' => '開始時刻は06:00〜22:45の15分刻みで選んでください。',
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
