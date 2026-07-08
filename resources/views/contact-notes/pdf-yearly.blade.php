<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'ipag';
            font-weight: normal;
            src: url({{ storage_path('fonts/ipaexg.ttf') }}) format('truetype');
        }
        @font-face {
            font-family: 'ipag';
            font-weight: bold;
            src: url({{ storage_path('fonts/ipaexg.ttf') }}) format('truetype');
        }
        body { font-family: 'ipag', sans-serif; font-size: 10px; color: #000; margin: 0; padding: 20px; line-height: 1.5; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 10px; border-bottom: 1.5px solid #000; padding-bottom: 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.meta td { border: 0.5px solid #333; padding: 4px 6px; font-size: 9.5px; }
        table.meta td.lbl { background: #eee; width: 80px; }
        .note { border: 0.5px solid #333; margin-bottom: 8px; page-break-inside: avoid; }
        .note .hd { background: #e8e8e8; padding: 3px 6px; font-size: 10px; border-bottom: 0.5px solid #333; }
        .note .hd .status { font-size: 8.5px; color: #555; float: right; }
        .note .bd { padding: 6px; }
        .note .row { margin-bottom: 4px; }
        .note .sub-lbl { font-size: 8.5px; color: #555; }
        .note .msg { white-space: pre-wrap; margin: 2px 0 4px; }
        .note .facts { font-size: 9px; color: #333; }
        .note .home { background: #f7f2e8; border: 0.5px solid #bbb; padding: 4px 6px; margin-top: 4px; font-size: 9px; }
        .note .home .sub-lbl { color: #7a5c00; }
        .footer-note { font-size: 8.5px; color: #666; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>連絡帳（{{ $year }}年 年間記録）</h1>

    <table class="meta">
        <tr>
            <td class="lbl">児童氏名</td>
            <td>{{ $child->name }}</td>
            <td class="lbl">事業所名</td>
            <td>{{ $facility->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">対象期間</td>
            <td>{{ $year }}年1月1日 〜 {{ $year }}年12月31日</td>
            <td class="lbl">出力日</td>
            <td>{{ now()->format('Y年n月j日') }}（全{{ $notes->count() }}件）</td>
        </tr>
    </table>

    @php
        $conditionLabels = ['good' => '良好', 'normal' => '普通', 'poor' => '不調'];
        $progressLabels  = \App\Models\ContactNote::GOAL_PROGRESS_LABELS;
    @endphp

    @foreach ($notes as $note)
        <div class="note">
            <div class="hd">
                <span class="status">
                    @if ($note->isPublished())
                        {{ optional($note->published_at)->format('n/j H:i') }} 公開
                        @if ($note->read_at) ／ 保護者既読 @endif
                    @elseif ($note->guardian_message || $note->meal_note || $note->health_note)
                        未公開（下書き）
                    @else
                        家庭からの連絡のみ
                    @endif
                </span>
                <strong>{{ $note->date->format('Y年n月j日') }}（{{ ['日','月','火','水','木','金','土'][$note->date->dayOfWeek] }}）</strong>
                @if ($note->supportRecord?->condition)
                    　様子：{{ $conditionLabels[$note->supportRecord->condition] ?? '' }}
                @endif
                @if ($note->goal_progress)
                    　目標への手応え：{{ $progressLabels[$note->goal_progress] ?? '' }}
                @endif
            </div>
            <div class="bd">
                @if ($note->guardian_message)
                    <div class="row">
                        <span class="sub-lbl">■ 施設より（記入：{{ $note->staff?->name ?? '―' }}）</span>
                        <div class="msg">{{ $note->guardian_message }}</div>
                    </div>
                @endif

                @php
                    $activities = $note->supportRecord?->programs?->map(
                        fn ($p) => $p->name . ($p->pivot->duration_minutes ? "（{$p->pivot->duration_minutes}分）" : '')
                    )->implode('、');
                @endphp
                @if ($activities || $note->meal_note || $note->health_note)
                    <div class="facts">
                        @if ($activities)活動：{{ $activities }}　@endif
                        @if ($note->meal_note)食事：{{ $note->meal_note }}　@endif
                        @if ($note->health_note)体調：{{ $note->health_note }}@endif
                    </div>
                @endif

                @if ($note->guardian_submitted_at)
                    <div class="home">
                        <span class="sub-lbl">■ 家庭より（{{ optional($note->guardian_submitted_at)->format('n/j H:i') }}）</span>
                        <div>
                            @if ($note->home_temperature)体温：{{ $note->home_temperature }}℃　@endif
                            @if ($note->home_sleep)睡眠：{{ $note->home_sleep }}　@endif
                            @if ($note->home_medication)服薬：{{ $note->home_medication }}　@endif
                            @if ($note->home_condition)朝の様子：{{ $note->home_condition }}@endif
                        </div>
                        @if ($note->guardian_comment)
                            <div class="msg">{{ $note->guardian_comment }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <p class="footer-note">
        ※ 本書は {{ $facility->name ?? '' }} の連絡帳システムから出力した年間記録です。「未公開（下書き）」の記載は保護者に配信されていません。
    </p>
</body>
</html>
