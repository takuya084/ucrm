<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreUsageRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'                    => ['required', 'date'],
            'records'                 => ['required', 'array'],
            'records.*.child_id'      => ['required', 'exists:children,id'],
            'records.*.status'        => ['required', 'in:attended,absent,absent_notice,cancel'],
            'records.*.absent_reason' => ['nullable', 'string', 'max:200'],
            'records.*.pickup_done'   => ['boolean'],
            'records.*.dropoff_done'  => ['boolean'],
            'records.*.billing_target'=> ['boolean'],
            'records.*.memo'          => ['nullable', 'string', 'max:200'],
            'records.*.check_in_time' => ['nullable', 'date_format:H:i'],
            'records.*.check_out_time'=> ['nullable', 'date_format:H:i'],
            'records.*.is_school_day' => ['boolean'],
            'records.*.service_type'  => ['nullable', 'in:houday,jidou'],
        ];
    }

    public function attributes(): array
    {
        return [
            'date'    => '日付',
            'records' => '出席記録',
        ];
    }
}
