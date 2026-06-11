<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecipientCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 自施設で登録した他社事業所のみ紐付け可能
        $facilityId = $this->user()?->staff?->facility_id;

        return [
            'certificate_number'          => ['nullable', 'string', 'max:20'],
            'municipality'                => ['nullable', 'string', 'max:50'],
            'valid_from'                  => ['nullable', 'date'],
            'valid_to'                    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'monthly_limit'               => ['required', 'integer', 'min:1', 'max:31'],
            'disability_support_category' => ['nullable', 'string', 'max:100'],
            'issue_date'                  => ['nullable', 'date'],
            'status'                      => ['required', 'in:active,expired,pending'],
            'copayment_rate'              => ['nullable', 'integer', 'min:0', 'max:100'],
            'copayment_cap_monthly'       => ['nullable', 'integer', 'min:0'],
            'is_cap_management_target'    => ['nullable', 'boolean'],
            'service_type'                => ['nullable', 'in:houday,jidou'],
            'municipality_code'           => ['nullable', 'string', 'max:6'],
            'external_facility_ids'       => ['nullable', 'array'],
            'external_facility_ids.*'     => [
                'integer',
                Rule::exists('external_facilities', 'id')->where('facility_id', $facilityId),
            ],
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
            'status'                   => 'ステータス',
            'copayment_rate'           => '自己負担割合',
            'copayment_cap_monthly'    => '上限月額',
            'is_cap_management_target' => '上限管理対象',
            'service_type'             => 'サービス種別',
            'municipality_code'        => '市区町村コード',
        ];
    }
}
