<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\YoyakuApiService;
use Illuminate\Http\Request;

class TransportGridController extends Controller
{
    public function show(Request $request, YoyakuApiService $api)
    {
        $input = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $facility = Facility::findOrFail($this->facilityId());
        abort_unless($facility->yoyaku_business_id, 422, 'p-yoyakuとの連携設定が必要です。');
        return response()->json($api->transportGrid('GET', [
            'business_id' => (int) $facility->yoyaku_business_id, 'date' => $input['date'],
        ], $facility->id))->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, YoyakuApiService $api)
    {
        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*' => ['required', 'array'], 'rows.*.user_id' => ['required', 'integer', 'min:1', 'distinct'],
            'rows.*.snapshot' => ['required', 'string', 'max:16384'], 'rows.*.action' => ['required', 'in:save,delete'],
            'rows.*.pickup_time' => ['sometimes', 'nullable', 'string', 'max:8'],
            'rows.*.pickup_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.pickup_staff_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rows.*.pickup_assistant_staff_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rows.*.dropoff_time' => ['sometimes', 'nullable', 'string', 'max:8'],
            'rows.*.dropoff_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.dropoff_staff_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rows.*.dropoff_assistant_staff_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $facility = Facility::findOrFail($this->facilityId());
        abort_unless($facility->yoyaku_business_id, 422, 'p-yoyakuとの連携設定が必要です。');
        return response()->json($api->transportGrid('PUT', [
            'business_id' => (int) $facility->yoyaku_business_id, ...$input,
        ], $facility->id));
    }
}
