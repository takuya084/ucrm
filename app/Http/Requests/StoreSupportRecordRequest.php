<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'child_id'                => ['required', 'exists:children,id'],
            'usage_record_id'         => ['nullable', 'exists:usage_records,id'],
            'date'                    => ['required', 'date'],
            'condition'               => ['required', 'in:good,normal,poor'],
            'behavior_note'           => ['nullable', 'string', 'max:2000'],
            'achievement_note'        => ['nullable', 'string', 'max:2000'],
            'challenge_note'          => ['nullable', 'string', 'max:2000'],
            'care_note'               => ['nullable', 'string', 'max:1000'],
            'next_action'             => ['nullable', 'string', 'max:1000'],
            'is_shared_with_guardian' => ['boolean'],
            'program_ids'             => ['nullable', 'array'],
            'program_ids.*'           => ['exists:programs,id'],
            'program_durations'       => ['nullable', 'array'],
            'program_durations.*'     => ['nullable', 'integer', 'min:5', 'max:180'],
        ];
    }

    public function attributes(): array
    {
        return [
            'child_id'   => '児童',
            'date'       => '日付',
            'condition'  => '様子',
            'program_ids'=> '実施プログラム',
        ];
    }
}
