<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChildScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $childId = $this->route('child')->id;

        return [
            'day_of_week'     => [
                'required',
                'in:mon,tue,wed,thu,fri,sat',
                // 同じ児童・同じ曜日の有効なスケジュールが重複しないようにチェック
                function ($attribute, $value, $fail) use ($childId) {
                    $exists = \App\Models\ChildSchedule::where('child_id', $childId)
                        ->where('day_of_week', $value)
                        ->whereNull('end_date')
                        ->exists();
                    if ($exists) {
                        $fail('この曜日はすでに登録されています。');
                    }
                },
            ],
            'start_date'      => ['required', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'          => ['required', 'in:regular,temporary,trial'],
            'pickup_required' => ['boolean'],
            'memo'            => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'day_of_week'     => '利用曜日',
            'start_date'      => '開始日',
            'end_date'        => '終了日',
            'status'          => '種別',
            'pickup_required' => '送迎有無',
        ];
    }
}
