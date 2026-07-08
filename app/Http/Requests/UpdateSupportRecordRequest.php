<?php

namespace App\Http\Requests;

use App\Models\ContactNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            // 連絡帳（保護者に公開される内容）
            'contact_note'                    => ['nullable', 'array'],
            'contact_note.meal_note'          => ['nullable', 'string', 'max:255'],
            'contact_note.health_note'        => ['nullable', 'string', 'max:255'],
            'contact_note.guardian_message'   => ['nullable', 'string', 'max:2000'],
            'contact_note.five_domain_tags'   => ['nullable', 'array'],
            'contact_note.five_domain_tags.*' => [Rule::in(array_keys(ContactNote::FIVE_DOMAIN_LABELS))],
            'contact_note.goal_progress'      => ['nullable', Rule::in(array_keys(ContactNote::GOAL_PROGRESS_LABELS))],
            'contact_note.publish_now'        => ['nullable', 'boolean'],
        ];
    }
}
