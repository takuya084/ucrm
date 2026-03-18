<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitoringRecordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'monitoring_date'     => ['required', 'date'],
            'period_from'         => ['nullable', 'date'],
            'period_to'           => ['nullable', 'date', 'after_or_equal:period_from'],
            'support_summary'     => ['nullable', 'string', 'max:3000'],
            'strengths'           => ['nullable', 'string', 'max:2000'],
            'challenges'          => ['nullable', 'string', 'max:2000'],
            'guardian_needs'      => ['nullable', 'string', 'max:2000'],
            'environmental_notes' => ['nullable', 'string', 'max:2000'],
            'next_review_date'    => ['nullable', 'date', 'after:monitoring_date'],
        ];
    }
}
