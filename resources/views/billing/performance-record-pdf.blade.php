<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'ipag';
            src: url({{ storage_path('fonts/ipag.ttf') }}) format('truetype');
        }
        body { font-family: 'ipag', sans-serif; font-size: 10px; color: #000; margin: 0; padding: 18px; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 10px; border-bottom: 1.5px solid #000; padding-bottom: 4px; }
        .meta { width: 100%; margin-bottom: 8px; }
        .meta td { padding: 2px 4px; font-size: 10px; vertical-align: top; }
        .meta .lbl { background: #eee; width: 90px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 0.5px solid #333; padding: 2px 3px; font-size: 9px; text-align: center; }
        table.grid th { background: #e8e8e8; font-weight: normal; }
        .wd-sat { color: #0044aa; }
        .wd-sun { color: #aa0000; }
        .mark { font-weight: bold; font-size: 11px; }
        .footer { margin-top: 10px; font-size: 10px; }
        .sign-box { border: 1px solid #333; height: 50px; margin-top: 6px; padding: 4px; }
        .totals { margin-top: 8px; }
        .totals td { padding: 3px 6px; border: 0.5px solid #333; font-size: 10px; }
    </style>
</head>
<body>
    <h1>サービス提供実績記録票</h1>

    <table class="meta">
        <tr>
            <td class="lbl">受給者証番号</td>
            <td>{{ $certificate?->certificate_number ?? '' }}</td>
            <td class="lbl">対象年月</td>
            <td>{{ \Carbon\Carbon::parse($yearMonth.'-01')->format('Y年n月') }}</td>
        </tr>
        <tr>
            <td class="lbl">児童氏名</td>
            <td>{{ $child->name }}</td>
            <td class="lbl">サービス種別</td>
            <td>{{ $detail->service_type === 'houday' ? '放課後等デイサービス' : '児童発達支援' }}</td>
        </tr>
        <tr>
            <td class="lbl">事業所名</td>
            <td>{{ $facility->name }}</td>
            <td class="lbl">事業所番号</td>
            <td>{{ $facility->facility_code }}</td>
        </tr>
        <tr>
            <td class="lbl">支給量（月）</td>
            <td>{{ $certificate?->monthly_limit ? $certificate->monthly_limit.'日' : '-' }}</td>
            <td class="lbl">当月利用日数</td>
            <td>{{ $detail->total_days }}日</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:5%">日</th>
                <th style="width:5%">曜</th>
                <th style="width:10%">登所</th>
                <th style="width:10%">退所</th>
                <th style="width:8%">欠席</th>
                <th style="width:8%">送迎(往)</th>
                <th style="width:8%">送迎(復)</th>
                <th style="width:8%">延長</th>
                <th style="width:12%">サービス内容</th>
                <th style="width:26%">備考 / 保護者確認印</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $r  = $row['record'];
                    $ur = $r?->usageRecord;
                    $absent = $ur && $ur->status === 'absent_notice';
                    $wdClass = $row['weekday'] === '土' ? 'wd-sat' : ($row['weekday'] === '日' ? 'wd-sun' : '');
                @endphp
                <tr>
                    <td class="{{ $wdClass }}">{{ $row['date'] }}</td>
                    <td class="{{ $wdClass }}">{{ $row['weekday'] }}</td>
                    <td>{{ $r && !$absent ? \Carbon\Carbon::parse($r->start_time)->format('H:i') : '' }}</td>
                    <td>{{ $r && !$absent ? \Carbon\Carbon::parse($r->end_time)->format('H:i') : '' }}</td>
                    <td><span class="mark">{{ $absent ? '●' : '' }}</span></td>
                    <td><span class="mark">{{ $r && $r->is_pickup ? '○' : '' }}</span></td>
                    <td><span class="mark">{{ $r && $r->is_dropoff ? '○' : '' }}</span></td>
                    <td><span class="mark">{{ $r && $r->is_extension ? '○' : '' }}</span></td>
                    <td style="font-size:8px">{{ $r?->serviceCodeMaster?->service_name ?? ($absent ? '欠席時対応' : '') }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="border-collapse: collapse;">
        <tr>
            <td>合計利用日数</td>
            <td><strong>{{ $detail->total_days }}</strong> 日</td>
            <td>合計単位数</td>
            <td><strong>{{ number_format($detail->total_units) }}</strong> 単位</td>
        </tr>
    </table>

    <div class="footer">
        <div>上記のとおり、サービスの提供を受けたことを確認します。</div>
        <div style="margin-top:4px">保護者署名欄：</div>
        <div class="sign-box"></div>
    </div>
</body>
</html>
