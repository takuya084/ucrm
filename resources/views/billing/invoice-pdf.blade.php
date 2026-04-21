<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'ipag';
            font-style: normal;
            font-weight: normal;
            src: url({{ storage_path('fonts/ipaexg.ttf') }}) format('truetype');
        }
        @font-face {
            font-family: 'ipag';
            font-style: normal;
            font-weight: bold;
            src: url({{ storage_path('fonts/ipaexg.ttf') }}) format('truetype');
        }
        body {
            font-family: 'ipag', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0;
            border-bottom: 2px solid #333;
            display: inline-block;
            padding-bottom: 5px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
            padding: 3px 0;
        }
        .info-left { width: 55%; }
        .info-right { width: 45%; text-align: right; }
        .guardian-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .facility-info {
            font-size: 10px;
            line-height: 1.6;
        }
        .amount-box {
            border: 2px solid #333;
            padding: 10px 15px;
            margin: 20px 0;
            text-align: center;
        }
        .amount-box .label {
            font-size: 12px;
            margin-bottom: 5px;
        }
        .amount-box .amount {
            font-size: 24px;
            font-weight: bold;
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.detail th,
        table.detail td {
            border: 1px solid #999;
            padding: 5px 8px;
            font-size: 10px;
        }
        table.detail th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        table.detail td.right {
            text-align: right;
        }
        .summary-table {
            width: 50%;
            margin-left: auto;
            margin-top: 15px;
            border-collapse: collapse;
        }
        .summary-table td {
            border: 1px solid #999;
            padding: 5px 8px;
            font-size: 11px;
        }
        .summary-table .label-cell {
            background: #f0f0f0;
            font-weight: bold;
            width: 50%;
        }
        .summary-table .value-cell {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>利用料請求書</h1>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-left">
                <div class="guardian-name">{{ $guardian->name }} 様</div>
                <div style="font-size: 10px; color: #666;">
                    ご利用児童：{{ $child->name }}<br>
                    対象期間：{{ $invoice->year_month }}
                </div>
            </td>
            <td class="info-right">
                <div class="facility-info">
                    <strong>{{ $facility->name }}</strong><br>
                    〒{{ $facility->address }}<br>
                    TEL: {{ $facility->tel }}
                    @if($facility->fax)
                        / FAX: {{ $facility->fax }}
                    @endif
                    <br>
                    発行日：{{ now()->format('Y年n月j日') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="label">ご請求金額（税込）</div>
        <div class="amount">&yen; {{ number_format($invoice->total_amount) }}</div>
    </div>

    @if($invoice->due_date)
    <div style="text-align: center; font-size: 11px; margin-bottom: 15px;">
        お支払期限：{{ $invoice->due_date->format('Y年n月j日') }}
    </div>
    @endif

    <div style="font-size: 11px; font-weight: bold; margin-top: 20px;">サービス利用明細</div>
    <table class="detail">
        <thead>
            <tr>
                <th>サービスコード</th>
                <th>サービス内容</th>
                <th>回数</th>
                <th>単位数/回</th>
                <th>合計単位数</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
            <tr>
                <td style="text-align: center;">{{ $line->service_code }}</td>
                <td>{{ $line->service_name }}</td>
                <td class="right">{{ $line->count }}</td>
                <td class="right">{{ number_format($line->units_per_count) }}</td>
                <td class="right">{{ number_format($line->total_units) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="label-cell">合計単位数</td>
            <td class="value-cell">{{ number_format($detail->total_units) }}</td>
        </tr>
        <tr>
            <td class="label-cell">単位数単価</td>
            <td class="value-cell">{{ $detail->unit_price_yen }} 円</td>
        </tr>
        <tr>
            <td class="label-cell">費用合計</td>
            <td class="value-cell">{{ number_format($detail->total_amount) }} 円</td>
        </tr>
        <tr>
            <td class="label-cell">給付費（保険負担分）</td>
            <td class="value-cell">{{ number_format($detail->insurance_amount) }} 円</td>
        </tr>
        <tr>
            <td class="label-cell">利用者負担額</td>
            <td class="value-cell"><strong>{{ number_format($detail->copayment_cap_applied) }} 円</strong></td>
        </tr>
        @if($invoice->other_charges > 0)
        <tr>
            <td class="label-cell">その他費用</td>
            <td class="value-cell">{{ number_format($invoice->other_charges) }} 円</td>
        </tr>
        @endif
        <tr>
            <td class="label-cell" style="font-size: 12px;">ご請求金額</td>
            <td class="value-cell" style="font-size: 14px;"><strong>{{ number_format($invoice->total_amount) }} 円</strong></td>
        </tr>
    </table>

    <div class="footer">
        <p>利用日数：{{ $detail->total_days }} 日</p>
        <p>上限月額：{{ number_format($detail->copayment_cap) }} 円</p>
        @if($invoice->notes)
        <p>備考：{{ $invoice->notes }}</p>
        @endif
    </div>
</body>
</html>
