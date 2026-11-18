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
        ]);
        $facility = Facility::findOrFail($this->facilityId());
        abort_unless($facility->yoyaku_business_id, 422, 'p-yoyakuとの連携設定が必要です。');
        return response()->json($api->transportGrid('PUT', [
            'business_id' => (int) $facility->yoyaku_business_id, ...$input,
        ], $facility->id));
    }
}
