<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\FacilityServiceSetting;
use App\Models\ServiceCodeMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacilityServiceSettingController extends Controller
{
    /** 加算・減算設定一覧 */
    public function index()
    {
        $facilityId = $this->facilityId();

        // 現在有効なサービスコード（加算・減算のみ）
        $today = now()->format('Y-m');
        $codes = ServiceCodeMaster::validAt($today)
            ->whereIn('category', ['addition', 'subtraction'])
            ->orderBy('service_type')
            ->orderBy('service_code')
            ->get(['id', 'service_type', 'service_code', 'service_name', 'unit_count', 'unit_type', 'category']);

        // 事業所の既存設定
        $settings = FacilityServiceSetting::where('facility_id', $facilityId)
            ->get()
            ->keyBy('service_code_master_id');

        // コードごとに enabled 状態をマージ
        $items = $codes->map(function ($code) use ($settings) {
            $setting = $settings->get($code->id);
            return [
                'id'                    => $code->id,
                'service_type'          => $code->service_type,
                'service_code'          => $code->service_code,
                'service_name'          => $code->service_name,
                'unit_count'            => $code->unit_count,
                'unit_type'             => $code->unit_type,
                'category'              => $code->category,
                'is_enabled'            => $setting?->is_enabled ?? false,
                'setting_id'            => $setting?->id,
            ];
        });

        return Inertia::render('Billing/Settings/ServiceCodes', [
            'items' => $items->values(),
        ]);
    }

    /** 一括更新 */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'settings'                          => ['required', 'array'],
            'settings.*.service_code_master_id' => ['required', 'exists:service_code_masters,id'],
            'settings.*.is_enabled'             => ['required', 'boolean'],
        ]);

        $facilityId = $this->facilityId();
        $today = now()->toDateString();

        foreach ($request->settings as $item) {
            FacilityServiceSetting::updateOrCreate(
                [
                    'facility_id'           => $facilityId,
                    'service_code_master_id' => $item['service_code_master_id'],
                ],
                [
                    'is_enabled'     => $item['is_enabled'],
                    'effective_from' => $today,
                    'effective_to'   => null,
                ]
            );
        }

        return back()->with(['message' => '加算・減算設定を更新しました。', 'status' => 'success']);
    }
}
