<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * スタッフ紐付けがない場合は最初の事業所IDにフォールバック
     */
    protected function facilityId(): ?int
    {
        return auth()->user()->staff?->facility_id
            ?? \App\Models\Facility::value('id');
    }
}
