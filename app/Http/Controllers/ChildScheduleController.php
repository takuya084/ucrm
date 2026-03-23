<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChildScheduleRequest;
use App\Http\Requests\UpdateChildScheduleRequest;
use App\Models\Child;
use App\Models\ChildSchedule;
use Inertia\Inertia;

class ChildScheduleController extends Controller
{
    /** 登録フォーム */
    public function create(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        // 既に登録済みの曜日を渡してUIで選択不可にする
        $registeredSchedules = $child->schedules()
            ->active()
            ->get(['id', 'day_of_week'])
            ->map(fn($s) => ['id' => $s->id, 'day_of_week' => $s->day_of_week])
            ->toArray();

        return Inertia::render('Children/Schedule/Create', [
            'child'              => $child->only('id', 'name'),
            'registeredDays'     => array_column($registeredSchedules, 'day_of_week'),
            'registeredSchedules' => $registeredSchedules,
        ]);
    }

    /** 登録処理 */
    public function store(StoreChildScheduleRequest $request, Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $child->schedules()->create($request->validated());

        return to_route('children.show', $child)
            ->with(['message' => '利用曜日を登録しました。', 'status' => 'success']);
    }

    /** 編集フォーム */
    public function edit(Child $child, ChildSchedule $schedule)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        abort_if($schedule->child_id !== $child->id, 404);

        return Inertia::render('Children/Schedule/Edit', [
            'child'    => $child->only('id', 'name'),
            'schedule' => $schedule,
        ]);
    }

    /** 更新処理 */
    public function update(UpdateChildScheduleRequest $request, Child $child, ChildSchedule $schedule)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        abort_if($schedule->child_id !== $child->id, 404);

        $schedule->update($request->validated());

        return to_route('children.show', $child)
            ->with(['message' => '利用曜日を更新しました。', 'status' => 'success']);
    }

    /** 削除 */
    public function destroy(Child $child, ChildSchedule $schedule)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        abort_if($schedule->child_id !== $child->id, 404);

        $schedule->delete();

        return to_route('children.show', $child)
            ->with(['message' => '利用曜日を削除しました。', 'status' => 'success']);
    }
}
