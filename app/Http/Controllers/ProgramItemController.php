<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramItem;
use Illuminate\Http\Request;

class ProgramItemController extends Controller
{
    /** プログラム項目を追加 */
    public function store(Request $request, Program $program)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'difficulty_order' => 'required|integer|min:0|max:999',
        ]);

        ProgramItem::create([
            'program_id'       => $program->id,
            'name'             => $request->name,
            'difficulty_order' => $request->difficulty_order,
        ]);

        return back()->with(['message' => '項目を追加しました。', 'status' => 'success']);
    }

    /** プログラム項目を削除 */
    public function destroy(ProgramItem $programItem)
    {
        $programItem->delete();

        return back()->with(['message' => '項目を削除しました。', 'status' => 'success']);
    }
}
