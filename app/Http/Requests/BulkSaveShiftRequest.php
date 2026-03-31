<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSaveShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entries'                => ['required', 'array'],
            'entries.*.staff_id'     => ['required', 'integer', 'exists:staff,id'],
            'entries.*.date'         => ['required', 'date'],
            'entries.*.start_time'   => ['nullable', 'date_format:H:i'],
            'entries.*.work_type'    => ['nullable', 'string', 'max:30'],
            'entries.*.note'         => ['nullable', 'string', 'max:200'],
            'day_notes'              => ['nullable', 'array'],
            'day_notes.*.date'       => ['required', 'date'],
            'day_notes.*.note'       => ['nullable', 'string', 'max:500'],
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
