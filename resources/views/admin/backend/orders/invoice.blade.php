<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_no }}</title>
    @php $accent = $company->primary_color ?: '#1e40af'; $cur = $company->currency_symbol ?: '৳'; @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; background: #eceff3; margin: 0; font-size: 13px; }
        .toolbar { max-width: 820px; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; }
        .btn { border: 0; border-radius: 6px; padding: .5rem 1.1rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-print { background: {{ $accent }}; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .sheet { max-width: 820px; margin: 1rem auto 3rem; background: #fff; padding: 40px 44px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .top { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { display: flex; gap: 14px; align-items: center; }
        .brand img { max-height: 60px; max-width: 120px; }
        .company-name { font-size: 24px; font-weight: 800; color: {{ $accent }}; line-height: 1.1; }
        .bill-word { font-size: 34px; font-weight: 800; color: #9aa3af; letter-spacing: 1px; }
        .company-meta { margin-top: 10px; color: #333; white-space: pre-line; line-height: 1.5; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        .meta-table { width: 300px; margin-left: auto; margin-top: 6px; }
        .meta-table th, .meta-table td { border: 1px solid #b9c0c9; padding: 5px 8px; text-align: center; font-size: 12px; }
        .meta-table th { background: #eef1f5; }
        .bill-to { margin-top: 26px; }
        .bill-to .label { background: #e7ebf0; font-weight: 700; padding: 5px 10px; display: inline-block; min-width: 220px; }
        .bill-to .line { padding: 3px 0; }
        .bill-to .line b { display: inline-block; width: 70px; }
        .items { margin-top: 20px; }
        .items th, .items td { border: 1px solid #b9c0c9; padding: 6px 8px; font-size: 12px; }
        .items th { background: #eef1f5; text-align: center; }
        .items td.num { text-align: right; }
        .items td.center { text-align: center; }
        .totals-row td { border: 1px solid #b9c0c9; padding: 8px; font-weight: 700; }
        .thanks { text-align: center; font-style: italic; }
        .inword { margin-top: 18px; font-size: 12px; }
        .footer { margin-top: 40px; text-align: center; color: #444; font-size: 12px; white-space: pre-line; line-height: 1.5; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('order.show', $order->id) }}" class="btn btn-back">← Back</a>
        <button class="btn btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
    </div>

    <div class="sheet">
        <div class="top">
            <div class="brand">
                @if($company->logo && file_exists(public_path('upload/company/'.$company->logo)))
                    <img src="{{ asset('upload/company/'.$company->logo) }}" alt="logo">
                @endif
                <div class="company-name">{{ $company->company_name }}</div>
            </div>
            <div class="bill-word">BILL</div>
        </div>

        <div class="company-meta">{{ $company->address }}@if($company->phone)
Mobil: {{ $company->phone }}@endif @if($company->email)
Email: {{ $company->email }}@endif</div>

        <table class="meta-table">
            <tr><th>INVOICE #</th><th>DATE</th></tr>
            <tr><td>{{ $order->order_no }}</td><td>{{ $order->order_date?->format('d/m/Y') ?: '' }}</td></tr>
            <tr><th>Client ID</th><th>TERMS</th></tr>
            <tr><td>{{ $order->customer->contact_code ?? '—' }}</td><td>{{ $company->default_terms ?: '—' }}</td></tr>
        </table>

        <div class="bill-to">
            <div class="label">BILL TO</div>
            <div class="line"><b>Name:</b> {{ $order->customer->business_name ?: $order->customer->name ?? '' }}</div>
            <div class="line"><b>Address:</b> {{ collect([$order->customer->address_line_1 ?? null, $order->customer->city ?? null, $order->customer->country ?? null])->filter()->implode(', ') }}</div>
            <div class="line"><b>Mobile:</b> {{ $order->customer->mobile ?? '' }}</div>
            <div class="line"><b>Email:</b> {{ $order->customer->email ?? '' }}</div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:36px">SL</th>
                    <th>Shipment No.</th>
                    <th>Shipping Mark</th>
                    <th>Item Description</th>
                    <th style="width:60px">Pkg/Ctn</th>
                    <th style="width:60px">Qty/KG</th>
                    <th style="width:60px">Rate/KG</th>
                    <th style="width:100px">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $i => $item)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $i === 0 ? $order->shipment_no : '' }}</td>
                    <td>{{ $i === 0 ? $order->shipping_mark : '' }}</td>
                    <td>{{ $item->item_description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ number_format($item->our_asking_price, 2) }}</td>
                    <td class="num">{{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
                @for($p = $order->items->count(); $p < 8; $p++)
                <tr><td class="center">{{ $p + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td></td><td class="num">-</td></tr>
                @endfor
                <tr class="totals-row">
                    <td colspan="5" class="thanks">{{ $company->footer_note }}</td>
                    <td colspan="2">TOTAL</td>
                    <td class="num">{{ $company->currency ?: 'BDT' }} {{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="inword"><b>In word:</b> {{ $amountWords }}</div>

        <div class="footer">{{ $company->footer_contact }}</div>
    </div>
</body>
</html>
