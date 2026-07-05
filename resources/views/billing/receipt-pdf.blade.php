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
            padding: 40px;
        }
        .receipt-no {
            text-align: right;
            font-size: 10px;
            color: #666;
        }
        .header {
            text-align: center;
            margin: 10px 0 30px;
        }
        .header h1 {
            font-size: 22px;
            margin: 0;
            letter-spacing: 12px;
            border-bottom: 2px solid #333;
            display: inline-block;
            padding-bottom: 6px;
        }
        .guardian-name {
            font-size: 15px;
            font-weight: bold;
            border-bottom: 1px solid #333;
            display: inline-block;
            padding: 0 30px 4px 4px;
            margin-bottom: 25px;
        }
        .amount-box {
            border: 2px solid #333;
            background: #f8f8f8;
            padding: 12px 20px;
            margin: 10px auto 25px;
            text-align: center;
            width: 65%;
        }
        .amount-box .amount {
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .amount-box .tax-note {
            font-size: 9px;
            color: #666;
            margin-top: 4px;
        }
        .desc-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .desc-table td {
            padding: 4px 0;
            font-size: 11px;
        }
        .desc-label {
            width: 20%;
            color: #666;
        }
        .facility-block {
            width: 100%;
            margin-top: 30px;
        }
        .facility-block td { vertical-align: top; }
        .facility-info {
            font-size: 10px;
            line-height: 1.7;
            text-align: right;
        }
        .stamp-box {
            border: 1px solid #999;
            width: 60px;
            height: 60px;
            margin-left: 10px;
            display: inline-block;
            vertical-align: middle;
            text-align: center;
            font-size: 9px;
            color: #bbb;
            line-height: 60px;
        }
    </style>
</head>
<body>
    <div class="receipt-no">
        領収書番号：{{ $receiptNumber }}<br>
        発行日：{{ now()->format('Y年n月j日') }}
    </div>

    <div class="header">
        <h1>領収書</h1>
    </div>

    <div class="guardian-name">{{ $guardian?->name ?? '保護者' }} 様</div>

    <div class="amount-box">
        <div class="amount">￥{{ number_format($amount) }} －</div>
        <div class="tax-note">（非課税）</div>
    </div>

    <table class="desc-table">
        <tr>
            <td class="desc-label">但し書き</td>
            <td>障害児通所支援 利用者負担金として（{{ $yearMonthLabel }}分／ご利用児童：{{ $child->name }}）</td>
        </tr>
        <tr>
            <td class="desc-label">領収日</td>
            <td>{{ $paidAtLabel }}</td>
        </tr>
        @if($invoice->payment_method)
        <tr>
            <td class="desc-label">受領方法</td>
            <td>{{ ['bank_transfer' => '振込', 'cash' => '現金', 'other' => 'その他'][$invoice->payment_method] ?? $invoice->payment_method }}</td>
        </tr>
        @endif
    </table>

    <p style="font-size: 11px;">上記の金額を正に領収いたしました。</p>

    <table class="facility-block">
        <tr>
            <td style="width: 45%;"></td>
            <td>
                <div class="facility-info">
                    <strong style="font-size: 12px;">{{ $facility->name }}</strong><br>
                    事業所番号：{{ $facility->facility_code }}<br>
                    {{ $facility->address }}<br>
                    TEL: {{ $facility->tel }}
                    <span class="stamp-box">印</span>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
