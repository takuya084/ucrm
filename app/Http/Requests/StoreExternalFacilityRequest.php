<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_type'    => ['required', 'in:after_school,child_development,visit_support,home_visit,other'],
            'facility_number' => [
                'required', 'string', 'size:10', 'regex:/^[0-9]{10}$/',
                Rule::unique('external_facilities', 'facility_number')
                    ->where('facility_id', auth()->user()->facility_id),
            ],
            'name'            => ['required', 'string', 'max:100'],
            'name_kana'       => ['nullable', 'string', 'max:100'],
            'satellite_type'  => ['required', 'in:main,satellite'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'fax'             => ['nullable', 'string', 'max:20'],
            'postal_code'     => ['nullable', 'string', 'max:10'],
            'address'         => ['nullable', 'string', 'max:200'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'service_type'    => 'サービス種類',
            'facility_number' => '事業所番号',
            'name'            => '事業所名',
            'name_kana'       => '事業所名（かな）',
            'satellite_type'  => 'サテライト区分',
            'phone'           => '電話番号',
            'fax'             => 'FAX番号',
            'postal_code'     => '郵便番号',
            'address'         => '住所',
            'notes'           => '備考',
        ];
    }
}
