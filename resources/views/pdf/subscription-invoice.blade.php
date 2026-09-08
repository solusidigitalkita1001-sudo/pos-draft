<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->order_id }}</title>
    <style>
        @page { margin: 32px 40px; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1a1a1a;
            font-size: 12px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
        }
        .header .brand {
            display: table-cell;
            vertical-align: top;
        }
        .header .brand .name {
            font-size: 20px;
            font-weight: bold;
        }
        .header .doc-info {
            display: table-cell;
            vertical-align: top;
            text-align: right;
        }
        .header .doc-info .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
            background-color: {{ $statusColor }};
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
        }
        .meta-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .meta-table td.label {
            width: 160px;
            color: #666666;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .items-table th {
            text-align: left;
            border-bottom: 2px solid #1a1a1a;
            padding: 8px 4px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 10px 4px;
            border-bottom: 1px solid #e0e0e0;
        }
        .items-table .amount-col {
            text-align: right;
        }
        .totals {
            width: 100%;
            margin-top: 8px;
        }
        .totals td {
            padding: 6px 4px;
        }
        .totals .label {
            text-align: right;
            color: #666666;
        }
        .totals .value {
            text-align: right;
            width: 140px;
        }
        .totals .grand-total .label,
        .totals .grand-total .value {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #1a1a1a;
            padding-top: 10px;
        }
        .footer {
            margin-top: 48px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
            font-size: 10px;
            color: #999999;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            <div class="name">{{ config('app.name') }}</div>
            <div>Tagihan Langganan SaaS</div>
        </div>
        <div class="doc-info">
            <div class="title">{{ $title }}</div>
            <div>{{ $invoice->order_id }}</div>
            <div class="status">{{ $invoice->status->label() }}</div>
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Ditagihkan kepada</td>
            <td>{{ $organization->name }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal dibuat</td>
            <td>{{ $invoice->created_at->translatedFormat('d F Y, H:i') }}</td>
        </tr>
        @if ($invoice->paid_at)
            <tr>
                <td class="label">Tanggal dibayar</td>
                <td>{{ $invoice->paid_at->translatedFormat('d F Y, H:i') }}</td>
            </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th>Periode</th>
                <th class="amount-col">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Paket {{ $invoice->plan->name }}</td>
                <td>{{ $invoice->billing_period->label() }}</td>
                <td class="amount-col">{{ $formattedAmount }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand-total">
            <td class="label">Total</td>
            <td class="value">{{ $formattedAmount }}</td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan basah.
        Untuk pertanyaan mengenai tagihan ini, hubungi tim dukungan {{ config('app.name') }}.
    </div>
</body>
</html>
