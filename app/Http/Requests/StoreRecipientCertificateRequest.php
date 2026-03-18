<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipientCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate_number'          => ['nullable', 'string', 'max:20'],
            'municipality'                => ['nullable', 'string', 'max:50'],
            'valid_from'                  => ['nullable', 'date'],
            'valid_to'                    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'monthly_limit'               => ['required', 'integer', 'min:1', 'max:31'],
            'disability_support_category' => ['nullable', 'string', 'max:100'],
            'issue_date'                  => ['nullable', 'date'],
            'status'                      => ['required', 'in:active,expired,pending'],
        ];
    }

    public function attributes(): array
    {
        return [
            'certificate_number' => '受給者証番号',
            'municipality'       => '市区町村',
            'valid_from'         => '有効期間（開始）',
            'valid_to'           => '有効期間（終了）',
            'monthly_limit'      => '月あたり支給量',
            'issue_date'         => '交付日',
            'status'             => 'ステータス',
        ];
    }
}
