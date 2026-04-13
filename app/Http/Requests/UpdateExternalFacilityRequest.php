<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('external_facility')?->id;

        return [
            'service_type'    => ['required', 'in:after_school,child_development,visit_support,home_visit,other'],
            'facility_number' => [
                'required', 'string', 'size:10', 'regex:/^[0-9]{10}$/',
                Rule::unique('external_facilities', 'facility_number')
                    ->where('facility_id', auth()->user()->facility_id)
                    ->ignore($id),
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
        return (new StoreExternalFacilityRequest())->attributes();
    }
}
