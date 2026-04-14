<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'ipag';
            src: url({{ storage_path('fonts/ipag.ttf') }}) format('truetype');
        }
        body { font-family: 'ipag', sans-serif; font-size: 10.5px; color: #000; margin: 0; padding: 22px; line-height: 1.5; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 12px; border-bottom: 1.5px solid #000; padding-bottom: 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.meta td { border: 0.5px solid #333; padding: 4px 6px; font-size: 10px; vertical-align: top; }
        table.meta td.lbl { background: #eee; width: 90px; font-weight: normal; }
        .section { border: 0.5px solid #333; margin-bottom: 8px; }
        .section .hd { background: #e8e8e8; padding: 4px 6px; font-weight: bold; font-size: 10.5px; border-bottom: 0.5px solid #333; }
        .section .bd { padding: 8px; min-height: 55px; white-space: pre-wrap; font-size: 10.5px; }
        .sign { margin-top: 14px; border: 0.5px solid #333; padding: 8px; }
        .sign .row { margin-bottom: 10px; }
        .sign .lbl { display: inline-block; width: 110px; font-size: 10.5px; }
        .sign .line { display: inline-block; border-bottom: 0.5px solid #333; width: 260px; height: 16px; vertical-align: bottom; }
        .sign .stamp { display: inline-block; border: 0.5px solid #333; width: 44px; height: 44px; text-align: center; line-height: 44px; font-size: 9px; color: #666; margin-left: 10px; vertical-align: middle; }
        .agree-text { font-size: 10.5px; margin: 8px 0; }
    </style>
</head>
<body>
    <h1>個別支援計画書</h1>

    <table class="meta">
        <tr>
            <td class="lbl">児童氏名</td>
            <td>{{ $child->name }}</td>
            <td class="lbl">作成日</td>
            <td>{{ optional($plan->plan_date)->format('Y年n月j日') }}</td>
        </tr>
        <tr>
            <td class="lbl">事業所名</td>
            <td>{{ $facility->name ?? '' }}</td>
            <td class="lbl">事業所番号</td>
            <td>{{ $facility->facility_code ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">有効期間</td>
            <td colspan="3">
                {{ optional($plan->valid_from)->format('Y年n月j日') }}
                　〜
                {{ optional($plan->valid_to)->format('Y年n月j日') }}
            </td>
        </tr>
        <tr>
            <td class="lbl">作成担当者</td>
            <td colspan="3">{{ $plan->staff?->name ?? '' }}</td>
        </tr>
    </table>

    <div class="section">
        <div class="hd">長期目標</div>
        <div class="bd">{{ $plan->long_term_goal }}</div>
    </div>

    <div class="section">
        <div class="hd">短期目標</div>
        <div class="bd">{{ $plan->short_term_goal }}</div>
    </div>

    <div class="section">
        <div class="hd">支援方針</div>
        <div class="bd">{{ $plan->support_policy }}</div>
    </div>

    <div class="section">
        <div class="hd">支援内容・プログラム</div>
        <div class="bd" style="min-height: 110px;">{{ $plan->program_content }}</div>
    </div>

    <div class="sign">
        <div class="agree-text">上記の個別支援計画について説明を受け、内容に同意します。</div>
        <div class="row">
            <span class="lbl">同意年月日：</span>
            <span class="line"></span>
        </div>
        <div class="row">
            <span class="lbl">保護者氏名：</span>
            <span class="line"></span>
            <span class="stamp">印</span>
        </div>
    </div>
</body>
</html>
