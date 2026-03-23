<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'  => ['required', 'in:admin,leader,staff'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'  => '氏名',
            'email' => 'メールアドレス',
            'role'  => '役割',
        ];
    }
}
