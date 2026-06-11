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
        if ($error = $this->ensureConsent($child)) {
            return $error;
        }
        $draft = $this->ai->generateSupportPlanDraft($child);

        if ($draft === null) {
            return response()->json(['error' => 'AI下書き生成に失敗しました。APIキーの設定をご確認ください。'], 503);
        }

        return response()->json($draft);
    }

    public function monitoring(Child $child): JsonResponse
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        if ($error = $this->ensureConsent($child)) {
            return $error;
        }
        $draft = $this->ai->generateMonitoringDraft($child);

        if ($draft === null) {
            return response()->json(['error' => 'AI下書き生成に失敗しました。APIキーの設定をご確認ください。'], 503);
        }

        return response()->json($draft);
    }

    /**
     * 外部AIへの支援記録送信には保護者の同意記録を必須とする（個人情報保護法対応）
     */
    private function ensureConsent(Child $child): ?JsonResponse
    {
        if ($child->ai_draft_consented_at) {
            return null;
        }

        return response()->json([
            'error' => '保護者のAI利用同意が記録されていないため、AI下書きを生成できません。児童情報の編集画面で同意を記録してください。',
        ], 403);
    }
}
