<?php

namespace App\Services;

use App\Models\Child;
use App\Models\SupportPlan;
use App\Models\MonitoringRecord;
use App\Models\SupportRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    private string $apiKey;
    private string $model;
    private int    $timeout;

    public function __construct()
    {
        $this->apiKey  = config('services.openai.api_key') ?? '';
        $this->model   = config('services.openai.model', 'gpt-4o-mini');
        $this->timeout = (int) config('services.openai.timeout', 30);
    }

    /**
     * 個別支援計画の下書きを生成
     * @return array{long_term_goal:string, short_term_goal:string, support_policy:string, program_content:string}|null
     */
    public function generateSupportPlanDraft(Child $child): ?array
    {
        $previousPlan    = SupportPlan::where('child_id', $child->id)->orderBy('plan_date', 'desc')->first();
        $recentRecords   = SupportRecord::where('child_id', $child->id)
            ->where('date', '>=', now()->subMonths(3)->toDateString())
            ->orderBy('date', 'desc')
            ->limit(20)
            ->get();
        $latestMonitoring = MonitoringRecord::where('child_id', $child->id)->orderBy('monitoring_date', 'desc')->first();

        $userPrompt = $this->buildSupportPlanPrompt($child, $previousPlan, $recentRecords, $latestMonitoring);

        $systemPrompt = <<<EOT
あなたは放課後等デイサービスの個別支援計画作成を支援する専門家AIです。
提供された情報を元に、次の個別支援計画の草案を作成してください。
専門的かつ保護者にも伝わりやすい表現で、具体的に記述してください。

必ず以下のJSON形式のみで返してください（余分な説明文は不要です）：
{"long_term_goal":"...","short_term_goal":"...","support_policy":"...","program_content":"..."}
EOT;

        return $this->callApi($systemPrompt, $userPrompt);
    }

    /**
     * モニタリング記録の下書きを生成
     * @return array{support_summary:string, strengths:string, challenges:string, guardian_needs:string}|null
     */
    public function generateMonitoringDraft(Child $child): ?array
    {
        $latestPlan      = SupportPlan::where('child_id', $child->id)->orderBy('plan_date', 'desc')->first();
        $lastMonitoring  = MonitoringRecord::where('child_id', $child->id)->orderBy('monitoring_date', 'desc')->first();
        $periodFrom      = $lastMonitoring?->monitoring_date ?? now()->subMonths(6)->toDateString();
        $recentRecords   = SupportRecord::where('child_id', $child->id)
            ->where('date', '>=', $periodFrom)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        $userPrompt = $this->buildMonitoringPrompt($child, $latestPlan, $lastMonitoring, $recentRecords);

        $systemPrompt = <<<EOT
あなたは放課後等デイサービスのモニタリング記録作成を支援する専門家AIです。
提供された情報を元に、モニタリング記録の草案を作成してください。
客観的な事実と支援者の観点から、専門的に記述してください。

必ず以下のJSON形式のみで返してください（余分な説明文は不要です）：
{"support_summary":"...","strengths":"...","challenges":"...","guardian_needs":"..."}
EOT;

        return $this->callApi($systemPrompt, $userPrompt);
    }

    // ── private ──────────────────────────────────────────────────────────

    private function buildSupportPlanPrompt(
        Child $child,
        ?SupportPlan $prev,
        $records,
        ?MonitoringRecord $monitoring
    ): string {
        $conditionMap = ['good' => '良好', 'normal' => '普通', 'poor' => '不調'];

        $lines = [];
        $lines[] = "【児童情報】";
        $lines[] = "名前：{$child->name}、学年：{$child->grade}";
        $lines[] = "障がい種別：" . ($child->disability_type ?: 'なし');
        $lines[] = "配慮事項：" . ($child->care_note ?: 'なし');

        if ($prev) {
            $lines[] = "\n【前回の個別支援計画】";
            $lines[] = "長期目標：{$prev->long_term_goal}";
            $lines[] = "短期目標：{$prev->short_term_goal}";
            $lines[] = "支援方針：{$prev->support_policy}";
            $lines[] = "支援内容：{$prev->program_content}";
        }

        if ($records->isNotEmpty()) {
            $lines[] = "\n【最近3ヶ月の支援記録（新しい順）】";
            foreach ($records as $r) {
                $cond = $conditionMap[$r->condition] ?? '';
                $parts = array_filter([
                    $r->behavior_note     ? "様子：{$r->behavior_note}" : null,
                    $r->achievement_note  ? "できたこと：{$r->achievement_note}" : null,
                    $r->challenge_note    ? "課題：{$r->challenge_note}" : null,
                    $r->next_action       ? "申し送り：{$r->next_action}" : null,
                ]);
                if ($parts) {
                    $lines[] = "{$r->date}（{$cond}）" . implode('　', $parts);
                }
            }
        }

        if ($monitoring) {
            $lines[] = "\n【最新モニタリング記録（{$monitoring->monitoring_date}）】";
            $lines[] = "支援まとめ：{$monitoring->support_summary}";
            $lines[] = "強み：{$monitoring->strengths}";
            $lines[] = "課題：{$monitoring->challenges}";
        }

        return implode("\n", $lines);
    }

    private function buildMonitoringPrompt(
        Child $child,
        ?SupportPlan $plan,
        ?MonitoringRecord $lastMonitoring,
        $records
    ): string {
        $conditionMap = ['good' => '良好', 'normal' => '普通', 'poor' => '不調'];

        $lines = [];
        $lines[] = "【児童情報】";
        $lines[] = "名前：{$child->name}、学年：{$child->grade}";
        $lines[] = "障がい種別：" . ($child->disability_type ?: 'なし');

        if ($plan) {
            $lines[] = "\n【現在の個別支援計画】";
            $lines[] = "長期目標：{$plan->long_term_goal}";
            $lines[] = "短期目標：{$plan->short_term_goal}";
            $lines[] = "支援方針：{$plan->support_policy}";
        }

        if ($lastMonitoring) {
            $lines[] = "\n【前回モニタリング記録（{$lastMonitoring->monitoring_date}）】";
            $lines[] = "支援まとめ：{$lastMonitoring->support_summary}";
            $lines[] = "強み：{$lastMonitoring->strengths}";
            $lines[] = "課題：{$lastMonitoring->challenges}";
        }

        if ($records->isNotEmpty()) {
            $lines[] = "\n【対象期間の支援記録（新しい順）】";
            foreach ($records as $r) {
                $cond  = $conditionMap[$r->condition] ?? '';
                $parts = array_filter([
                    $r->behavior_note    ? "様子：{$r->behavior_note}" : null,
                    $r->achievement_note ? "できたこと：{$r->achievement_note}" : null,
                    $r->challenge_note   ? "課題：{$r->challenge_note}" : null,
                    $r->next_action      ? "申し送り：{$r->next_action}" : null,
                ]);
                if ($parts) {
                    $lines[] = "{$r->date}（{$cond}）" . implode('　', $parts);
                }
            }
        }

        return implode("\n", $lines);
    }

    private function callApi(string $systemPrompt, string $userPrompt): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI: APIキーが設定されていません');
            return null;
        }

        try {
            $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $this->model,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'temperature' => 0.7,
            ]);

            if (! $response->successful()) {
                Log::warning('OpenAI: APIエラー', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            // JSONブロック（```json...```）が含まれる場合に対応
            $content = preg_replace('/^```json\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/', '', $content);

            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('OpenAI: JSONパース失敗', ['content' => $content]);
                return null;
            }

            return $decoded;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenAI: 接続エラー', ['message' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('OpenAI: 予期せぬエラー', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
