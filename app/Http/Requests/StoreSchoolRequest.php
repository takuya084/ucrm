<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:100'],
            'name_kana'        => ['nullable', 'string', 'max:100'],
            'address'          => ['nullable', 'string', 'max:200'],
            'school_type'      => ['required', 'in:elementary,junior_high,special_needs,other'],
            'end_time_regular' => ['nullable', 'date_format:H:i'],
            'end_time_early'   => ['nullable', 'date_format:H:i'],
            'memo'             => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'             => '学校名',
            'name_kana'        => '学校名（かな）',
            'address'          => '住所',
            'school_type'      => '学校種別',
            'end_time_regular' => '通常下校時刻',
            'end_time_early'   => '早退・短縮時下校時刻',
            'memo'             => 'メモ',
        ];
    }
}
