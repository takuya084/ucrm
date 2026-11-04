<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\AreaCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 地域区分マスタ（級地コード→単価）の管理。
 * 全事業所共通データ。告示に基づく正式な単価が確認できた分だけ入力する。
 */
class AreaCategoryController extends Controller
{
    public function index()
    {
        $categories = AreaCategory::orderBy('code')->get(['id', 'code', 'label', 'unit_price', 'valid_from', 'valid_to']);

        return Inertia::render('Billing/Settings/AreaCategories', [
            'categories' => $categories,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'categories'              => ['required', 'array'],
            'categories.*.id'         => ['required', 'exists:area_categories,id'],
            'categories.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        foreach ($request->categories as $item) {
            AreaCategory::find($item['id'])?->update([
                'unit_price' => $item['unit_price'],
            ]);
        }

        return back()->with(['message' => '地域区分マスタを更新しました。', 'status' => 'success']);
    }
}
