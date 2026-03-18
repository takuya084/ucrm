<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\MonitoringRecord;
use App\Models\Child;
use App\Http\Requests\StoreMonitoringRecordRequest;
use App\Http\Requests\UpdateMonitoringRecordRequest;

class MonitoringRecordController extends Controller
{
    public function create(Request $request, Child $child)
    {
        $this->authorizeChild($child);

        $child->load(['monitoringRecords' => fn($q) => $q->orderBy('monitoring_date', 'desc')->limit(1)]);

        return Inertia::render('MonitoringRecords/Create', [
            'child'       => $child->only(['id', 'name']),
            'lastRecord'  => $child->monitoringRecords->first(),
        ]);
    }

    public function store(StoreMonitoringRecordRequest $request, Child $child)
    {
        $this->authorizeChild($child);

        $data = $request->validated();
        $data['child_id'] = $child->id;
        $data['staff_id'] = auth()->user()->staff?->id;

        MonitoringRecord::create($data);

        return redirect()->route('children.show', $child->id)
            ->with(['message' => 'モニタリング記録を登録しました。', 'status' => 'success']);
    }

    public function show(Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $monitoringRecord->load('staff:id,name');

        return Inertia::render('MonitoringRecords/Show', [
            'child'  => $child->only(['id', 'name']),
            'record' => $monitoringRecord,
        ]);
    }

    public function edit(Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        return Inertia::render('MonitoringRecords/Edit', [
            'child'  => $child->only(['id', 'name']),
            'record' => $monitoringRecord,
        ]);
    }

    public function update(UpdateMonitoringRecordRequest $request, Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $monitoringRecord->update($request->validated());

        return redirect()->route('children.monitoring.show', [$child->id, $monitoringRecord->id])
            ->with(['message' => 'モニタリング記録を更新しました。', 'status' => 'success']);
    }

    public function destroy(Child $child, MonitoringRecord $monitoringRecord)
    {
        $this->authorizeChild($child);
        abort_if($monitoringRecord->child_id !== $child->id, 404);

        $monitoringRecord->delete();

        return redirect()->route('children.show', $child->id)
            ->with(['message' => 'モニタリング記録を削除しました。', 'status' => 'success']);
    }

    private function authorizeChild(Child $child): void
    {
        $facilityId = auth()->user()->staff?->facility_id;
        if ($facilityId) {
            abort_if($child->facility_id !== $facilityId, 403);
        }
    }
}
