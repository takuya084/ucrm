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
                'id', 'name', 'address', 'tel', 'fax', 'capacity_per_day', 'yoyaku_business_id',
                'facility_code', 'service_type', 'area_unit_price', 'designated_date', 'administrator_name',
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
            'fax'                => ['nullable', 'string', 'max:20'],
            'capacity_per_day'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'yoyaku_business_id' => ['nullable', 'integer', 'min:1'],
            'facility_code'      => ['nullable', 'string', 'max:10'],
            'service_type'       => ['nullable', 'in:houday,jidou,both'],
            'area_unit_price'    => ['nullable', 'numeric', 'min:0', 'max:20'],
            'designated_date'    => ['nullable', 'date'],
            'administrator_name' => ['nullable', 'string', 'max:100'],
        ]);

        $facility->update($validated);

        return back()->with(['message' => '施設情報を更新しました。', 'status' => 'success']);
    }
}
