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
        body { font-family: 'ipag', sans-serif; font-size: 10.5px; color: #000; margin: 0; padding: 20px; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 12px; letter-spacing: 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { border: 0.5px solid #333; padding: 4px 6px; font-size: 10.5px; }
        .meta .lbl { background: #eee; width: 110px; }
        .section-title { font-size: 11px; font-weight: bold; margin: 12px 0 4px; }
        .result-box { border: 0.5px solid #333; padding: 6px 8px; margin-bottom: 8px; font-size: 10.5px; line-height: 1.7; }
        .check { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; text-align: center; font-weight: bold; margin-right: 4px; vertical-align: middle; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 0.5px solid #333; padding: 4px 6px; font-size: 10px; }
        table.grid th { background: #e8e8e8; font-weight: normal; text-align: center; }
        table.grid td.num { text-align: right; }
        table.grid td.c { text-align: center; }
        table.grid tr.sum td { background: #f5f5f5; font-weight: bold; }
        .footer { margin-top: 14px; font-size: 10.5px; }
        .sign { display: inline-block; border: 0.5px solid #333; padding: 8px 14px; margin-left: 10px; text-align: center; }
    </style>
</head>
<body>
    @php
        $yearMonth = $management->year_month;
        $ymJa = \Carbon\Carbon::parse($yearMonth . '-01')->format('Y年n月');
        $result = (string) ($management->management_result ?? '');
        $today = \Carbon\Carbon::now()->format('Y年n月j日');
        $total = $details->sum('total_amount');
        $totalCopay = $details->sum('copayment_amount');
        $totalAdjusted = $details->sum('adjusted_amount');
    @endphp

    <h1>利用者負担上限額管理結果票</h1>

    <table class="meta">
        <tr>
            <td class="lbl">対象年月</td>
            <td style="width:28%">{{ $ymJa }}</td>
            <td class="lbl">市町村番号</td>
            <td>{{ $certificate?->municipality_code ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">受給者証番号</td>
            <td>{{ $certificate?->certificate_number ?? '' }}</td>
            <td class="lbl">支給決定障害者等氏名</td>
            <td>{{ $guardian?->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">支給決定に係る<br>障害児氏名</td>
            <td>{{ $child->name }}</td>
            <td class="lbl">利用者負担上限月額</td>
            <td>{{ number_format($management->cap_amount ?? 0) }} 円</td>
        </tr>
    </table>

    <div class="section-title">上限額管理事業所</div>
    <table class="meta">
        <tr>
            <td class="lbl">事業所番号</td>
            <td style="width:28%">{{ $facility?->facility_code ?? '' }}</td>
            <td class="lbl">事業所名</td>
            <td>{{ $facility?->name ?? '' }}</td>
        </tr>
    </table>

    <div class="section-title">利用者負担上限額管理結果</div>
    <div class="result-box">
        <div>
            <span class="check">{{ $result === '1' ? '✓' : '' }}</span>
            1　管理事業所で利用者負担額を充当したため、他事業所の利用者負担は発生しない。
        </div>
        <div>
            <span class="check">{{ $result === '2' ? '✓' : '' }}</span>
            2　利用者負担額の合算額が、負担上限月額以下のため、調整事務は行わない。
        </div>
        <div>
            <span class="check">{{ $result === '3' ? '✓' : '' }}</span>
            3　利用者負担額の合算額が、負担上限月額を超過するため、下記のとおり調整した。
        </div>
    </div>

    <div class="section-title">利用者負担額集計・調整欄</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:6%">項番</th>
                <th style="width:18%">事業所番号</th>
                <th>事業所名</th>
                <th style="width:15%">総費用額</th>
                <th style="width:15%">利用者負担額</th>
                <th style="width:18%">管理結果後の<br>利用者負担額</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($details as $i => $d)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">
                        @if ($d->billable_facility_type === \App\Models\ExternalFacility::class)
                            {{ $d->billableFacility?->facility_number ?? '' }}
                        @else
                            {{ $d->billableFacility?->facility_code ?? '' }}
                        @endif
                    </td>
                    <td>
                        {{ $d->facility_name }}
                        @if ($d->is_managing_facility)
                            <span style="font-size:9px;color:#666">（管理）</span>
                        @endif
                    </td>
                    <td class="num">{{ number_format($d->total_amount ?? 0) }}</td>
                    <td class="num">{{ number_format($d->copayment_amount ?? 0) }}</td>
                    <td class="num">{{ number_format($d->adjusted_amount ?? 0) }}</td>
                </tr>
            @endforeach
            @for ($i = $details->count(); $i < 4; $i++)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
            <tr class="sum">
                <td class="c" colspan="3">合計</td>
                <td class="num">{{ number_format($total) }}</td>
                <td class="num">{{ number_format($totalCopay) }}</td>
                <td class="num">{{ number_format($totalAdjusted) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div>上記のとおり、利用者負担額の管理結果を通知します。</div>
        <div style="margin-top:12px">
            作成日： {{ $today }}
            <span class="sign">管理事業所印</span>
        </div>
        @if ($management->remarks)
            <div style="margin-top:10px; font-size:10px;">備考：{{ $management->remarks }}</div>
        @endif
    </div>
</body>
</html>
