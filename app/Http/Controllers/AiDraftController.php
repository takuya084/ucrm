<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiDraftController extends Controller
{
    public function __construct(private OpenAiService $ai) {}

    /**
     * 連絡帳の保護者向けメッセージ下書き。
     * 保存前のフォーム内容から生成できるよう、内部記録はリクエストボディで受け取る。
     */
    public function contactNote(Child $child, Request $request): JsonResponse
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        if ($error = $this->ensureConsent($child)) {
            return $error;
        }

        $context = $request->validate([
            'condition'        => ['nullable', 'string', 'max:20'],
            'behavior_note'    => ['nullable', 'string', 'max:2000'],
            'achievement_note' => ['nullable', 'string', 'max:2000'],
            'program_names'    => ['nullable', 'array'],
            'program_names.*'  => ['string', 'max:100'],
        ]);

        if (blank($context['behavior_note'] ?? null) && blank($context['achievement_note'] ?? null)) {
            return response()->json(['error' => '行動・様子メモ か できたこと を記入してからAI下書きを実行してください。'], 422);
        }

        $draft = $this->ai->generateContactNoteDraft($child, $context);

        if ($draft === null) {
            return response()->json(['error' => 'AI下書き生成に失敗しました。APIキーの設定をご確認ください。'], 503);
        }

        return response()->json($draft);
    }

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
