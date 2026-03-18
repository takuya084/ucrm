<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:50'],
            'category'         => ['required', 'in:physical,cognitive,social,life_skills,other'],
            'description'      => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'is_active'        => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'             => 'プログラム名',
            'category'         => 'カテゴリ',
            'duration_minutes' => '標準時間（分）',
            'is_active'        => '有効/無効',
        ];
    }
}
