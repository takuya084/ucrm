<?php

namespace App\Http\Controllers;

use App\Models\ShiftLabel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftLabelController extends Controller
{
    public function index()
    {
        $labels = ShiftLabel::where('facility_id', $this->facilityId())
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'name', 'is_off', 'display_order']);

        return Inertia::render('ShiftLabels/Index', [
            'labels' => $labels,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:30',
            'is_off'        => 'boolean',
            'display_order' => 'integer|min:0',
        ], [], [
            'name' => 'ラベル名',
        ]);

        $facilityId = $this->facilityId();

        if (ShiftLabel::where('facility_id', $facilityId)->where('name', $request->name)->exists()) {
            return back()->with(['message' => 'このラベル名は既に登録されています。', 'status' => 'danger']);
        }

        ShiftLabel::create([
            'facility_id'   => $facilityId,
            'name'          => $request->name,
            'is_off'        => $request->boolean('is_off'),
            'display_order' => $request->input('display_order', 0),
        ]);

        return back()->with(['message' => 'ラベルを追加しました。', 'status' => 'success']);
    }

    public function destroy(ShiftLabel $shiftLabel)
    {
        abort_if($shiftLabel->facility_id !== $this->facilityId(), 403);

        if ($shiftLabel->is_off && in_array($shiftLabel->name, ['休み', '有給'], true)) {
            return back()->with(['message' => 'このラベルは削除できません。', 'status' => 'danger']);
        }

        $shiftLabel->delete();

        return back()->with(['message' => 'ラベルを削除しました。', 'status' => 'success']);
    }
}
