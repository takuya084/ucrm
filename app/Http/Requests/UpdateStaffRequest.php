<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100'],
            'role'      => ['required', 'in:admin,leader,staff'],
            'is_active'        => ['required', 'boolean'],
            'qualifications'   => ['nullable', 'array'],
            'qualifications.*' => ['string', 'in:' . implode(',', array_keys(\App\Models\StaffQualification::TYPES))],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'      => '氏名',
            'role'      => '役割',
            'is_active' => 'ステータス',
        ];
    }
}
