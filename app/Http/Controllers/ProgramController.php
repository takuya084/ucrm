<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Models\Program;
use Inertia\Inertia;

class ProgramController extends Controller
{
    /** プログラム一覧 */
    public function index()
    {
        $facilityId = $this->facilityId();

        $programs = Program::where('facility_id', $facilityId)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'duration_minutes', 'is_active']);

        return Inertia::render('Programs/Index', [
            'programs' => $programs,
        ]);
    }

    /** 登録フォーム */
    public function create()
    {
        return Inertia::render('Programs/Create');
    }

    /** 登録処理 */
    public function store(StoreProgramRequest $request)
    {
        Program::create(array_merge(
            $request->validated(),
            ['facility_id' => $this->facilityId()]
        ));

        return to_route('programs.index')
            ->with(['message' => 'プログラムを登録しました。', 'status' => 'success']);
    }

    /** 編集フォーム */
    public function edit(Program $program)
    {
        abort_if($program->facility_id !== $this->facilityId(), 403);
        return Inertia::render('Programs/Edit', [
            'program' => $program->load('items'),
        ]);
    }

    /** 更新処理 */
    public function update(UpdateProgramRequest $request, Program $program)
    {
        abort_if($program->facility_id !== $this->facilityId(), 403);
        $program->update($request->validated());

        return to_route('programs.index')
            ->with(['message' => 'プログラムを更新しました。', 'status' => 'success']);
    }

    /** 削除 */
    public function destroy(Program $program)
    {
        abort_if($program->facility_id !== $this->facilityId(), 403);
        $program->delete();

        return to_route('programs.index')
            ->with(['message' => 'プログラムを削除しました。', 'status' => 'success']);
    }
}
