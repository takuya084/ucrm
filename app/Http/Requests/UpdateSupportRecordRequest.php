<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupportRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
}
