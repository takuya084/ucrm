<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'child_id'       => ['required', 'integer', 'exists:children,id'],
            'guardian_id'    => ['nullable', 'integer', 'exists:guardians,id'],
            'contact_method' => ['required', 'in:tel,line,email,in_person,other'],
            'contacted_at'   => ['required', 'date'],
            'category'       => ['required', 'in:schedule,support,billing,complaint,other'],
            'subject'        => ['nullable', 'string', 'max:200'],
            'content'        => ['required', 'string', 'max:2000'],
            'response'       => ['nullable', 'string', 'max:2000'],
            'status'         => ['required', 'in:open,in_progress,closed'],
            'is_escalated'   => ['boolean'],
        ];
    }
}
