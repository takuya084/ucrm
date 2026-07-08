<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ContactNote;
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
        $recentNotes     = ContactNote::where('child_id', $child->id)
            ->where('date', '>=', $periodFrom)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        $userPrompt = $this->buildMonitoringPrompt($child, $latestPlan, $lastMonitoring, $recentRecords, $recentNotes);

        $systemPrompt = <<<EOT
あなたは放課後等デイサービスのモニタリング記録作成を支援する専門家AIです。
提供された情報を元に、モニタリング記録の草案を作成してください。
客観的な事実と支援者の観点から、専門的に記述してください。

必ず以下のJSON形式のみで返してください（余分な説明文は不要です）：
{"support_summary":"...","strengths":"...","challenges":"...","guardian_needs":"..."}
EOT;

        return $this->callApi($systemPrompt, $userPrompt);
    }

    /**
     * 連絡帳の保護者向けメッセージ下書きを生成
     * 内部記録（様子・できたこと・実施プログラム）を保護者向けの表現に変換する。
     * @param array{condition?:string, behavior_note?:string, achievement_note?:string, program_names?:array} $context
     * @return array{guardian_message:string}|null
     */
    public function generateContactNoteDraft(Child $child, array $context): ?array
    {
        $conditionMap = ['good' => '良好', 'normal' => '普通', 'poor' => '不調'];

        $lines   = [];
        $lines[] = "【今日の内部記録】";
        $lines[] = "様子：" . ($conditionMap[$context['condition'] ?? ''] ?? '不明');
        if (!empty($context['program_names'])) {
            $lines[] = "実施した活動：" . implode('、', $context['program_names']);
        }
        if (!empty($context['behavior_note'])) {
            $lines[] = "行動・様子メモ：{$context['behavior_note']}";
        }
        if (!empty($context['achievement_note'])) {
            $lines[] = "できたこと：{$context['achievement_note']}";
        }

        $systemPrompt = <<<EOT
あなたは放課後等デイサービスの連絡帳（保護者向け）の文章作成を支援するAIです。
職員の内部記録を元に、保護者に今日の様子を伝えるメッセージを作成してください。

ルール：
- 記録にある事実だけを使い、書かれていないことを創作しない
- 温かく丁寧な語り口で、専門用語を避ける
- 課題や問題行動の指摘・支援上の見立ては書かない（それらは職員が直接伝える）
- 児童は「お子さま」と表現する（実名・「本児」は使わない）
- 200〜300字程度

必ず以下のJSON形式のみで返してください（余分な説明文は不要です）：
{"guardian_message":"..."}
EOT;

        return $this->callApi($systemPrompt, $this->scrubName($child, implode("\n", $lines)));
    }

    // ── private ──────────────────────────────────────────────────────────

    private function buildSupportPlanPrompt(
        Child $child,
        ?SupportPlan $prev,
        $records,
        ?MonitoringRecord $monitoring
    ): string {
        $conditionMap = ['good' => '良好', 'normal' => '普通', 'poor' => '不調'];

        // 仮名化: 実名は外部APIへ送信しない（要配慮個人情報の保護）
        $lines = [];
        $lines[] = "【児童情報】";
        $lines[] = "対象児童（以下「本児」）、学年：{$child->grade}";
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

        return $this->scrubName($child, implode("\n", $lines));
    }

    private function buildMonitoringPrompt(
        Child $child,
        ?SupportPlan $plan,
        ?MonitoringRecord $lastMonitoring,
        $records,
        $contactNotes = null
    ): string {
        $conditionMap = ['good' => '良好', 'normal' => '普通', 'poor' => '不調'];

        // 仮名化: 実名は外部APIへ送信しない（要配慮個人情報の保護）
        $lines = [];
        $lines[] = "【児童情報】";
        $lines[] = "対象児童（以下「本児」）、学年：{$child->grade}";
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

        // 連絡帳から: 短期目標への手応えの集計と保護者コメント（保護者ニーズの材料）
        if ($contactNotes && $contactNotes->isNotEmpty()) {
            $progressCounts = collect(ContactNote::GOAL_PROGRESS_LABELS)
                ->map(fn ($label, $key) => $label . '：' . $contactNotes->where('goal_progress', $key)->count() . '件')
                ->values();
            $lines[] = "\n【短期目標への手応え（連絡帳の日次評価の集計）】";
            $lines[] = $progressCounts->implode('　');

            $comments = $contactNotes->filter(fn ($n) => filled($n->guardian_comment))->take(10);
            if ($comments->isNotEmpty()) {
                $lines[] = "\n【保護者からの連絡帳コメント（新しい順）】";
                foreach ($comments as $n) {
                    $lines[] = "{$n->date->format('Y-m-d')}：{$n->guardian_comment}";
                }
            }

            $homeNotes = $contactNotes->filter(fn ($n) => filled($n->home_condition) || filled($n->home_sleep))->take(10);
            if ($homeNotes->isNotEmpty()) {
                $lines[] = "\n【家庭からの様子（新しい順）】";
                foreach ($homeNotes as $n) {
                    $parts = array_filter([
                        $n->home_condition ? "朝の様子：{$n->home_condition}" : null,
                        $n->home_sleep     ? "睡眠：{$n->home_sleep}" : null,
                    ]);
                    $lines[] = "{$n->date->format('Y-m-d')}　" . implode('　', $parts);
                }
            }
        }

        return $this->scrubName($child, implode("\n", $lines));
    }

    /**
     * プロンプト中に支援記録等の自由記述経由で混入した児童の実名を「本児」に置換する。
     * 姓名の分かち書きには対応できないため、記録入力時から実名を書かない運用を推奨。
     */
    private function scrubName(Child $child, string $text): string
    {
        $tokens = array_filter([
            $child->name,
            $child->name_kana,
            str_replace(['　', ' '], '', (string) $child->name),
            str_replace(['　', ' '], '', (string) $child->name_kana),
        ]);

        foreach (array_unique($tokens) as $token) {
            if (mb_strlen($token) >= 2) {
                $text = str_replace($token, '本児', $text);
            }
        }

        return $text;
    }

    private function callApi(string $systemPrompt, string $userPrompt): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI: APIキーが設定されていません');
            return null;
        }

        try {
            $response = Http::withHeaders([
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
