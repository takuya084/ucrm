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
        body { font-family: 'ipag', sans-serif; font-size: 10px; color: #000; margin: 0; padding: 16px; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 10px; letter-spacing: 3px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { border: 0.5px solid #333; padding: 3px 6px; font-size: 10px; }
        .meta .lbl { background: #eee; width: 100px; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 0.5px solid #333; padding: 3px 5px; font-size: 9.5px; }
        table.grid th { background: #e8e8e8; font-weight: normal; text-align: center; }
        table.grid td.num { text-align: right; }
        table.grid td.c { text-align: center; }
        table.grid tr.sum td { background: #f5f5f5; font-weight: bold; }
        .footer { margin-top: 10px; font-size: 9.5px; }
        .sign { display: inline-block; border: 0.5px solid #333; padding: 6px 14px; margin-left: 10px; text-align: center; }
        .cap-badge { display: inline-block; font-size: 8.5px; padding: 0 4px; border: 0.5px solid #999; color: #555; }
    </style>
</head>
<body>
    <h1>利用者負担額一覧表</h1>

    <table class="meta">
        <tr>
            <td class="lbl">対象年月</td>
            <td style="width:22%">{{ $ymJa }}</td>
            <td class="lbl">事業所番号</td>
            <td style="width:22%">{{ $facility->facility_code ?? '' }}</td>
            <td class="lbl">事業所名</td>
            <td>{{ $facility->name }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:5%">項番</th>
                <th style="width:11%">市町村番号</th>
                <th style="width:14%">受給者証番号</th>
                <th>支給決定障害者等氏名</th>
                <th>支給決定に係る障害児氏名</th>
                <th style="width:13%">総費用額</th>
                <th style="width:13%">利用者負担額</th>
                <th style="width:14%">管理結果後の<br>利用者負担額</th>
                <th style="width:6%">備考</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $r['municipality_code'] }}</td>
                    <td class="c">{{ $r['certificate_number'] }}</td>
                    <td>{{ $r['guardian_name'] }}</td>
                    <td>{{ $r['child_name'] }}</td>
                    <td class="num">{{ number_format($r['total_amount']) }}</td>
                    <td class="num">{{ number_format($r['copayment_amount']) }}</td>
                    <td class="num">{{ number_format($r['adjusted_amount']) }}</td>
                    <td class="c">
                        @if ($r['is_cap_target'])
                            <span class="cap-badge">上限管理</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="c" colspan="9" style="padding:14px; color:#888;">対象月の請求データがありません。</td>
                </tr>
            @endforelse
            @if ($rows->count() > 0)
                <tr class="sum">
                    <td class="c" colspan="5">合計（{{ $rows->count() }} 名）</td>
                    <td class="num">{{ number_format($totalFee) }}</td>
                    <td class="num">{{ number_format($totalCopay) }}</td>
                    <td class="num">{{ number_format($totalAdjusted) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div>上記のとおり、利用者負担額の一覧を通知します。</div>
        <div style="margin-top:10px">
            作成日： {{ \Carbon\Carbon::now()->format('Y年n月j日') }}
            <span class="sign">事業所印</span>
        </div>
    </div>
</body>
</html>
