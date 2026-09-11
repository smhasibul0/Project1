<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carton label {{ $order->order_no }}</title>
    {{-- The QR is drawn on the server as an SVG, so it prints sharp at any size
         and needs no internet on the machine doing the printing. --}}
    @php
        // Drop the XML prolog: this SVG is being inlined into an HTML document.
        $qr = preg_replace('/^<\?xml[^>]*\?>\s*/', '', (string) QrCode::format('svg')
            ->size(340)->margin(1)->errorCorrection('M')->generate($trackUrl));
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #eceff3; color: #111; margin: 0; font-size: 13px; }
        .toolbar { max-width: 760px; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; align-items: center; }
        .btn { border: 0; border-radius: 6px; padding: .5rem 1.1rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-print { background: #1e40af; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .copies { font-size: 13px; color: #334155; margin-right: auto; }
        .copies input { width: 60px; border: 1px solid #cbd5e1; border-radius: 6px; padding: .35rem .5rem; font-size: 13px; }

        .sheet { max-width: 760px; margin: 1rem auto 3rem; }
        .label {
            background: #fff; border: 2px solid #111; border-radius: 6px;
            width: 360px; padding: 18px 16px 14px; margin: 0 auto 14px; text-align: center;
            page-break-inside: avoid; break-inside: avoid;
        }
        .label .company { font-size: 13px; font-weight: 700; letter-spacing: .02em; margin-bottom: 10px; text-transform: uppercase; }
        .label .qr svg { display: block; margin: 0 auto; width: 240px; height: 240px; }
        .label .mark { font-size: 30px; font-weight: 800; letter-spacing: .04em; margin-top: 10px; line-height: 1.1; word-break: break-all; }
        .label .order { font-size: 15px; font-weight: 700; margin-top: 4px; }
        .label .meta { font-size: 11px; color: #333; margin-top: 8px; line-height: 1.45; }
        .label .scan-hint { font-size: 10px; color: #555; margin-top: 8px; border-top: 1px dashed #999; padding-top: 6px; }
        .ctn-box { margin-top: 8px; font-size: 12px; font-weight: 700; }
        .ctn-box span { display: inline-block; border: 1px solid #111; min-width: 46px; padding: 2px 6px; margin-left: 4px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; }
            .label { margin: 0 auto 10px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span class="copies">
            Labels to print:
            <input type="number" id="copies" min="1" max="200" value="{{ max(1, (int) round($order->totalCartons())) ?: 1 }}">
            <span style="color:#64748b">one per carton</span>
        </span>
        <a href="{{ route('order.show', $order->id) }}" class="btn btn-back">← Back</a>
        <button class="btn btn-print" onclick="window.print()">🖨 Print labels</button>
    </div>

    <div class="sheet" id="sheet">
        <div class="label" id="labelTemplate">
            <div class="company">{{ $company->company_name }}</div>
            <div class="qr">{!! $qr !!}</div>
            <div class="mark">{{ $order->shipping_mark ?: $order->order_no }}</div>
            <div class="order">{{ $order->order_no }}</div>
            <div class="meta">
                {{ $order->customer->business_name ?: $order->customer->name ?? '' }}
                @if($order->country_of_loading)<br>From: {{ $order->country_of_loading }}@endif
            </div>
            <div class="ctn-box">CTN <span>&nbsp;</span> of {{ rtrim(rtrim(number_format($order->totalCartons(), 2), '0'), '.') ?: '—' }}</div>
            <div class="scan-hint">Scan to track this shipment</div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // One label per carton: stamp out as many copies as asked for.
        const sheet = document.getElementById('sheet');
        const template = document.getElementById('labelTemplate');
        const copies = document.getElementById('copies');
        const original = template.outerHTML;

        function render() {
            const n = Math.min(200, Math.max(1, parseInt(copies.value, 10) || 1));
            sheet.innerHTML = original.repeat(n);
        }

        copies.addEventListener('input', render);
        render();
    });
    </script>
</body>
</html>
