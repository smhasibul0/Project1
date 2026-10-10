<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation {{ $quotation->quotation_no }}</title>
    @php $accent = $company->primary_color ?: '#1e40af'; $cur = $company->currency_symbol ?: '৳'; $pdf = $pdf ?? false; @endphp
    {{-- The customer's copy. Freight cost, duty, additional costs and profit are
         deliberately absent — this page must never show what the shipment costs us. --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: {!! $pdf ? "'DejaVu Sans', sans-serif" : 'Arial, Helvetica, sans-serif' !!}; color: #1a1a1a; background: #eceff3; margin: 0; font-size: {{ $pdf ? '11px' : '13px' }}; }
        .toolbar { max-width: 820px; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; }
        .btn { border: 0; border-radius: 6px; padding: .5rem 1.1rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-print { background: {{ $accent }}; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .btn-download { background: #fff; color: {{ $accent }}; border: 1px solid {{ $accent }}; }
        .sheet { max-width: 820px; margin: 1rem auto 3rem; background: #fff; padding: 40px 44px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .top td { vertical-align: top; padding: 0; }
        .top td.word { text-align: right; }
        .brand td { vertical-align: middle; padding: 0 14px 0 0; }
        .brand img { max-height: 60px; max-width: 120px; }
        .company-name { font-size: 24px; font-weight: 800; color: {{ $accent }}; line-height: 1.1; }
        .doc-word { font-size: 30px; font-weight: 800; color: #9aa3af; letter-spacing: 1px; }
        .company-meta { margin-top: 10px; color: #333; white-space: pre-line; line-height: 1.5; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        .meta-table { width: 320px; margin-left: auto; margin-top: 6px; }
        .meta-table th, .meta-table td { border: 1px solid #b9c0c9; padding: 5px 8px; text-align: center; font-size: 12px; }
        .meta-table th { background: #eef1f5; }
        .quote-to { margin-top: 26px; }
        .quote-to .label { background: #e7ebf0; font-weight: 700; padding: 5px 10px; display: inline-block; min-width: 220px; }
        .quote-to .line { padding: 3px 0; }
        .quote-to .line b { display: inline-block; width: 70px; }
        .items { margin-top: 20px; }
        .items th, .items td { border: 1px solid #b9c0c9; padding: 6px 8px; font-size: 12px; }
        .items th { background: #eef1f5; text-align: center; }
        .items td.num { text-align: right; }
        .items td.center { text-align: center; }
        .rate-box { margin-top: 18px; width: 340px; margin-left: auto; }
        .rate-box th, .rate-box td { border: 1px solid #b9c0c9; padding: 7px 9px; font-size: 12px; }
        .rate-box th { background: #eef1f5; text-align: left; }
        .rate-box td { text-align: right; }
        .rate-box tr.grand th, .rate-box tr.grand td { background: {{ $accent }}; color: #fff; font-weight: 700; font-size: 13px; }
        .inword { margin-top: 18px; font-size: 12px; }
        .note { margin-top: 22px; font-size: 12px; color: #333; white-space: pre-line; line-height: 1.5; }
        .terms { margin-top: 18px; font-size: 12px; }
        .terms .label { font-weight: 700; margin-bottom: 4px; }
        .sign { margin-top: 55px; font-size: 12px; }
        .sign td { padding: 0; }
        .sign td.right { text-align: right; }
        .sign div { border-top: 1px solid #6b7280; padding-top: 5px; width: 210px; text-align: center; display: inline-block; }
        .footer { margin-top: 40px; text-align: center; color: #444; font-size: 12px; white-space: pre-line; line-height: 1.5; }
        @if($pdf)
        /* A4 holds the whole document when the spacing is a little tighter. */
        .company-meta { margin-top: 6px; line-height: 1.35; }
        .bill-to, .quote-to { margin-top: 14px; }
        .items { margin-top: 12px; }
        .items th, .items td { padding: 4px 6px; }
        .rate-box { margin-top: 10px; }
        .rate-box th, .rate-box td { padding: 5px 8px; }
        .inword, .terms { margin-top: 10px; }
        .sign { margin-top: 36px; }
        .footer { margin-top: 22px; }
        @endif
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    @unless($pdf)
    <div class="toolbar">
        <a href="{{ route('quotation.show', $quotation->id) }}" class="btn btn-back">← Back</a>
        <a href="{{ route('quotation.pdf', $quotation->id) }}" class="btn btn-download">⬇ Download PDF</a>
        <button class="btn btn-print" onclick="window.print()">🖨 Print</button>
    </div>
    @endunless

    <div class="sheet">
        <table class="top">
            <tr>
                <td>
                    <table class="brand" style="width:auto"><tr>
                        @if($company->logo && file_exists(public_path('upload/company/'.$company->logo)))
                        <td><img src="{{ $pdf ? public_path('upload/company/'.$company->logo) : asset('upload/company/'.$company->logo) }}" alt="logo"></td>
                        @endif
                        <td><div class="company-name">{{ $company->company_name }}</div></td>
                    </tr></table>
                </td>
                <td class="word"><div class="doc-word">QUOTATION</div></td>
            </tr>
        </table>

        <div class="company-meta">{{ $company->address }}@if($company->phone){{ "\n" }}Mobil: {{ $company->phone }}@endif @if($company->email){{ "\n" }}Email: {{ $company->email }}@endif</div>

        <table class="meta-table">
            <tr><th>QUOTATION #</th><th>DATE</th></tr>
            <tr>
                <td>{{ $quotation->quotation_no }}</td>
                <td>{{ ($quotation->query_received_date ?: $quotation->created_at)?->format('d/m/Y') }}</td>
            </tr>
            <tr><th>Client ID</th><th>TERMS</th></tr>
            <tr>
                <td>{{ $quotation->customer->contact_code ?? '—' }}</td>
                <td>{{ $company->default_terms ?: '—' }}</td>
            </tr>
            <tr><th>TRANSPORT</th><th>LOADING FROM</th></tr>
            <tr>
                <td>{{ $quotation->transportationMode->name ?? '—' }}</td>
                <td>{{ $quotation->country_of_loading ?: '—' }}</td>
            </tr>
        </table>

        <div class="quote-to">
            <div class="label">QUOTATION FOR</div>
            <div class="line"><b>Name:</b> {{ $quotation->customer?->business_name ?: ($quotation->customer?->name ?? '') }}</div>
            <div class="line"><b>Address:</b> {{ collect([$quotation->customer->address_line_1 ?? null, $quotation->customer->city ?? null, $quotation->customer->country ?? null])->filter()->implode(', ') }}</div>
            <div class="line"><b>Mobile:</b> {{ $quotation->customer->mobile ?? '' }}</div>
            <div class="line"><b>Email:</b> {{ $quotation->customer->email ?? '' }}</div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:36px">SL</th>
                    <th style="width:90px">HS Code</th>
                    <th>Description of Goods</th>
                    <th style="width:70px">Packages</th>
                    <th style="width:75px">Net Wt (kg)</th>
                    <th style="width:75px">Gross Wt (kg)</th>
                    <th style="width:70px">CBM</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $i => $item)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td class="center">{{ $item->hs_code ?: '—' }}</td>
                    <td>{{ $item->description ?: '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ $item->net_weight ? rtrim(rtrim(number_format($item->net_weight, 3), '0'), '.') : '—' }}</td>
                    <td class="num">{{ $item->gross_weight ? rtrim(rtrim(number_format($item->gross_weight, 3), '0'), '.') : '—' }}</td>
                    <td class="num">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                </tr>
                @endforeach
                @for($p = $quotation->items->count(); $p < 6; $p++)
                <tr><td class="center">{{ $p + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                @endfor
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:700">TOTAL</td>
                    <td class="num" style="font-weight:700">{{ rtrim(rtrim(number_format($quotation->items->sum('package_quantity'), 2), '0'), '.') }}</td>
                    <td class="num" style="font-weight:700">{{ rtrim(rtrim(number_format($quotation->items->sum('net_weight'), 3), '0'), '.') }}</td>
                    <td class="num" style="font-weight:700">{{ rtrim(rtrim(number_format($quotation->items->sum('gross_weight'), 3), '0'), '.') }}</td>
                    <td class="num" style="font-weight:700">{{ rtrim(rtrim(number_format($quotation->total_cbm, 4), '0'), '.') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="rate-box">
            <tr>
                <th>Rate per CBM</th>
                <td>{{ $cur }} {{ number_format($quotation->sell_rate_per_cbm, 2) }}</td>
            </tr>
            <tr>
                <th>Total Volume</th>
                <td>{{ rtrim(rtrim(number_format($quotation->total_cbm, 4), '0'), '.') }} CBM</td>
            </tr>
            <tr class="grand">
                <th>Total Payable</th>
                <td>{{ $cur }} {{ number_format($quotation->customer_charge, 2) }}</td>
            </tr>
        </table>

        <div class="inword"><b>In words:</b> {{ $amountWords }}</div>

        <div class="terms">
            <div class="label">The rate quoted is all-inclusive</div>
            It covers freight, customs duty and taxes, and clearing — there is nothing further to pay
            on top of the total above.
        </div>

        @if($quotation->remarks)
        <div class="note"><b>Remarks:</b> {{ $quotation->remarks }}</div>
        @endif

        <table class="sign">
            <tr>
                <td><div>Customer's Signature</div></td>
                <td class="right"><div>For {{ $company->company_name }}</div></td>
            </tr>
        </table>

        @if($company->footer_note)
        <div class="footer">{{ $company->footer_note }}</div>
        @endif
    </div>
</body>
</html>
