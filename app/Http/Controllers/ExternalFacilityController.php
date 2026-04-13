<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExternalFacilityRequest;
use App\Http\Requests\UpdateExternalFacilityRequest;
use App\Models\ExternalFacility;
use Inertia\Inertia;

class ExternalFacilityController extends Controller
{
    public function index()
    {
        $externalFacilities = ExternalFacility::where('facility_id', $this->facilityId())
            ->orderBy('service_type')->orderBy('name_kana')->orderBy('name')->get();

        return Inertia::render('ExternalFacilities/Index', [
            'externalFacilities'  => $externalFacilities,
            'serviceTypeLabels'   => ExternalFacility::SERVICE_TYPE_LABELS,
            'satelliteTypeLabels' => ExternalFacility::SATELLITE_TYPE_LABELS,
        ]);
    }

    public function create()
    {
        return Inertia::render('ExternalFacilities/Create', [
            'serviceTypeLabels'   => ExternalFacility::SERVICE_TYPE_LABELS,
            'satelliteTypeLabels' => ExternalFacility::SATELLITE_TYPE_LABELS,
        ]);
    }

    public function store(StoreExternalFacilityRequest $request)
    {
        ExternalFacility::create(array_merge(
            $request->validated(),
            ['facility_id' => $this->facilityId()]
        ));

        return to_route('external-facilities.index')
            ->with(['message' => '他社事業所を登録しました。', 'status' => 'success']);
    }

    public function edit(ExternalFacility $externalFacility)
    {
        abort_if($externalFacility->facility_id !== $this->facilityId(), 403);

        return Inertia::render('ExternalFacilities/Edit', [
            'externalFacility'    => $externalFacility,
            'serviceTypeLabels'   => ExternalFacility::SERVICE_TYPE_LABELS,
            'satelliteTypeLabels' => ExternalFacility::SATELLITE_TYPE_LABELS,
        ]);
    }

    public function update(UpdateExternalFacilityRequest $request, ExternalFacility $externalFacility)
    {
        abort_if($externalFacility->facility_id !== $this->facilityId(), 403);

        $externalFacility->update($request->validated());

        return to_route('external-facilities.index')
            ->with(['message' => '他社事業所情報を更新しました。', 'status' => 'success']);
    }

    public function destroy(ExternalFacility $externalFacility)
    {
        abort_if($externalFacility->facility_id !== $this->facilityId(), 403);

        $externalFacility->delete();

        return to_route('external-facilities.index')
            ->with(['message' => '他社事業所を削除しました。', 'status' => 'success']);
    }
}
