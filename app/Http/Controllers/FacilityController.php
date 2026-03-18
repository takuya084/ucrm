<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacilityController extends Controller
{
    /** 施設設定画面 */
    public function edit()
    {
        $facilityId = $this->facilityId();
        $facility   = Facility::findOrFail($facilityId);

        return Inertia::render('Facility/Edit', [
            'facility' => $facility->only([
                'id', 'name', 'address', 'tel', 'capacity_per_day', 'yoyaku_business_id',
            ]),
        ]);
    }

    /** 施設設定の更新 */
    public function update(Request $request)
    {
        $facilityId = $this->facilityId();
        $facility   = Facility::findOrFail($facilityId);

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'address'            => ['nullable', 'string', 'max:200'],
            'tel'                => ['nullable', 'string', 'max:20'],
            'capacity_per_day'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'yoyaku_business_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $facility->update($validated);

        return back()->with(['message' => '施設情報を更新しました。', 'status' => 'success']);
    }
}
