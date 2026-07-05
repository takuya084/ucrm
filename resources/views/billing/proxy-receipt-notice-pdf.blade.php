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
            font-size: 18px;
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
        .body-text {
            margin: 20px 0;
            line-height: 1.8;
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.detail th,
        table.detail td {
            border: 1px solid #999;
            padding: 6px 8px;
            font-size: 11px;
        }
        table.detail th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        table.detail td.right { text-align: right; }
        .amount-box {
            border: 2px solid #333;
            padding: 10px 15px;
            margin: 20px 0;
            text-align: center;
            width: 60%;
        }
        .amount-box .label { font-size: 12px; margin-bottom: 5px; }
        .amount-box .amount { font-size: 22px; font-weight: bold; }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>障害児通所給付費 法定代理受領額通知書</h1>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-left">
                <div class="guardian-name">{{ $guardian?->name ?? '保護者' }} 様</div>
                <div style="font-size: 10px; color: #666;">
                    ご利用児童：{{ $child->name }}<br>
                    受給者証番号：{{ $certificate?->certificate_number ?? '―' }}<br>
                    サービス提供年月：{{ $yearMonthLabel }}
                </div>
            </td>
            <td class="info-right">
                <div class="facility-info">
                    <strong>{{ $facility->name }}</strong><br>
                    事業所番号：{{ $facility->facility_code }}<br>
                    {{ $facility->address }}<br>
                    TEL: {{ $facility->tel }}<br>
                    発行日：{{ now()->format('Y年n月j日') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="body-text">
        児童福祉法第21条の5の7第11項の規定に基づき、下記のとおり障害児通所給付費を
        {{ $guardian?->name ?? '保護者' }} 様に代わり当事業所が受領いたしましたので、通知します。
    </div>

    <table class="detail">
        <tr>
            <th>サービス提供年月</th>
            <th>サービス種別</th>
            <th>総費用額</th>
            <th>利用者負担額</th>
            <th>法定代理受領額（給付費）</th>
        </tr>
        <tr>
            <td style="text-align:center;">{{ $yearMonthLabel }}</td>
            <td style="text-align:center;">{{ $detail->service_type === 'houday' ? '放課後等デイサービス' : '児童発達支援' }}</td>
            <td class="right">{{ number_format($detail->total_amount) }}円</td>
            <td class="right">{{ number_format($detail->copayment_cap_applied) }}円</td>
            <td class="right">{{ number_format($detail->insurance_amount) }}円</td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="label">法定代理受領額</div>
        <div class="amount">{{ number_format($detail->insurance_amount) }} 円</div>
    </div>

    <div class="footer">
        ※ 本通知書は、事業者が市町村（国民健康保険団体連合会経由）から障害児通所給付費を代理受領したことをお知らせするものです。<br>
        ※ お支払いいただく利用者負担額は、別途お送りする利用料請求書をご確認ください。
    </div>
</body>
</html>
