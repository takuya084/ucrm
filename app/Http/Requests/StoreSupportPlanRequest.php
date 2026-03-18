<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportPlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'previous_plan_id'        => ['nullable', 'integer', 'exists:support_plans,id'],
            'plan_date'               => ['required', 'date'],
            'valid_from'              => ['nullable', 'date'],
            'valid_to'                => ['nullable', 'date', 'after_or_equal:valid_from'],
            'long_term_goal'          => ['nullable', 'string', 'max:2000'],
            'short_term_goal'         => ['nullable', 'string', 'max:2000'],
            'support_policy'          => ['nullable', 'string', 'max:2000'],
            'program_content'         => ['nullable', 'string', 'max:3000'],
            'guardian_agreement'      => ['boolean'],
            'guardian_agreement_date' => ['nullable', 'date'],
        ];
    }
}
