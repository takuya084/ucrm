<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;

class AiDraftController extends Controller
{
    public function __construct(private OpenAiService $ai) {}

    public function supportPlan(Child $child): JsonResponse
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $draft = $this->ai->generateSupportPlanDraft($child);

        if ($draft === null) {
            return response()->json(['error' => 'AI下書き生成に失敗しました。APIキーの設定をご確認ください。'], 503);
        }

        return response()->json($draft);
    }

    public function monitoring(Child $child): JsonResponse
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $draft = $this->ai->generateMonitoringDraft($child);

        if ($draft === null) {
            return response()->json(['error' => 'AI下書き生成に失敗しました。APIキーの設定をご確認ください。'], 503);
        }

        return response()->json($draft);
    }
}
