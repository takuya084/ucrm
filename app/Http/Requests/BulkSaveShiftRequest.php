<?php

namespace App\Http\Requests;

use App\Support\ShiftInput;
use App\Models\MonthlyShift;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class BulkSaveShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->user()?->staff;
        $shift = $this->route('shift');
        return $staff?->is_active && in_array($staff->role, ['admin', 'leader'], true)
            && $shift instanceof MonthlyShift && $shift->facility_id === $staff->facility_id && $shift->isDraft();
    }

    public function rules(): array
    {
        $shift = $this->route('shift');
        $facilityId = $this->user()->staff->facility_id;
        $start = Carbon::create($shift->year, $shift->month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->toDateString();
        $dateRules = ['required', 'date_format:Y-m-d', 'after_or_equal:'.$start->toDateString(), 'before_or_equal:'.$end];
        return [
            'entries'                => ['required', 'array'],
            'entries.*.staff_id'     => ['required', 'integer', Rule::exists('staff', 'id')->where('facility_id', $facilityId)->where('is_active', true)],
            'entries.*.date'         => $dateRules,
            'entries.*.start_time'   => ['nullable', 'date_format:H:i', Rule::in(ShiftInput::times())],
            'entries.*.work_type'    => ['nullable', 'string', 'max:30', ShiftInput::labelRule($facilityId)],
            'entries.*.note'         => ['nullable', 'string', 'max:200'],
            'day_notes'              => ['nullable', 'array'],
            'day_notes.*.date'       => $dateRules,
            'day_notes.*.note'       => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            ShiftInput::rejectOffTimes($validator, $this->input('entries'), 'entries', $this->user()->staff->facility_id);
        });
    }

    public function messages(): array
    {
        return [
            'entries.*.work_type.in' => '未登録の勤務区分は保存できません。',
            'entries.*.start_time.in' => '開始時刻は06:00〜22:45の15分刻みで選んでください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'entries.*.staff_id'   => 'スタッフ',
            'entries.*.date'       => '日付',
            'entries.*.start_time' => '開始時刻',
            'entries.*.work_type'  => '勤務区分',
            'entries.*.note'       => '備考',
            'day_notes.*.date'     => '日付',
            'day_notes.*.note'     => '日別備考',
        ];
    }
}
