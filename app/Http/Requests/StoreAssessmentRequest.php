<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'assessed_at'        => ['required', 'date'],
            'physical_condition' => ['nullable', 'string', 'max:3000'],
            'living_environment' => ['nullable', 'string', 'max:3000'],
            'child_intention'    => ['nullable', 'string', 'max:3000'],
            'guardian_intention' => ['nullable', 'string', 'max:3000'],
            'five_domains'       => ['nullable', 'array'],
            'notes'              => ['nullable', 'string', 'max:3000'],
        ];
    }
}
