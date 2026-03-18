<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\School;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function index()
    {
        $schools = School::orderBy('school_type')->orderBy('name_kana')->orderBy('name')->get();

        return Inertia::render('Schools/Index', [
            'schools'          => $schools,
            'schoolTypeLabels' => School::TYPE_LABELS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Schools/Create', [
            'schoolTypeLabels' => School::TYPE_LABELS,
        ]);
    }

    public function store(StoreSchoolRequest $request)
    {
        School::create($request->validated());

        return to_route('schools.index')
            ->with(['message' => '学校を登録しました。', 'status' => 'success']);
    }

    public function edit(School $school)
    {
        return Inertia::render('Schools/Edit', [
            'school'           => $school,
            'schoolTypeLabels' => School::TYPE_LABELS,
        ]);
    }

    public function update(UpdateSchoolRequest $request, School $school)
    {
        $school->update($request->validated());

        return to_route('schools.index')
            ->with(['message' => '学校情報を更新しました。', 'status' => 'success']);
    }

    public function destroy(School $school)
    {
        $school->delete();

        return to_route('schools.index')
            ->with(['message' => '学校を削除しました。', 'status' => 'success']);
    }
}
