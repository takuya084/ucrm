<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:50'],
            'name_kana'           => ['nullable', 'string', 'max:50', 'regex:/^[ァ-ヶー　 ]+$/u'],
            'gender'              => ['nullable', 'in:male,female,other'],
            'birthdate'           => ['nullable', 'date'],
            'grade'               => ['nullable', 'string', 'max:20'],
            'school_id'           => ['nullable', 'exists:schools,id'],
            'disability_type'     => ['nullable', 'string', 'max:100'],
            'disability_note'     => ['nullable', 'string', 'max:1000'],
            'allergy_note'        => ['nullable', 'string', 'max:1000'],
            'care_note'           => ['nullable', 'string', 'max:1000'],
            'pickup_required'     => ['boolean'],
            'pickup_address'      => ['nullable', 'string', 'max:200'],
            'pickup_area'         => ['nullable', 'string', 'max:100'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_status'     => ['required', 'in:active,suspended,ended'],
            'memo'                => ['nullable', 'string', 'max:2000'],
            'yoyaku_user_id'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                => '児童名',
            'name_kana'           => '児童名カナ',
            'gender'              => '性別',
            'birthdate'           => '生年月日',
            'grade'               => '学年',
            'school_id'           => '学校',
            'disability_type'     => '障がい種別',
            'pickup_required'     => '送迎有無',
            'contract_start_date' => '契約開始日',
            'contract_status'     => '契約状況',
        ];
    }
}
