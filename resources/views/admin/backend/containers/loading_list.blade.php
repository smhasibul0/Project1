<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loading List {{ $container->container_code }}</title>
    @php $company = \App\Models\CompanySetting::current(); $accent = $company->primary_color ?: '#1e40af'; @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; background: #eceff3; margin: 0; font-size: 12px; }
        .toolbar { max-width: 1100px; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; }
        .btn { border: 0; border-radius: 6px; padding: .5rem 1.1rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-print { background: {{ $accent }}; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .sheet { max-width: 1100px; margin: 1rem auto 3rem; background: #fff; padding: 32px 36px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .title { text-align: center; font-size: 20px; font-weight: 800; color: {{ $accent }}; margin-bottom: 2px; }
        .subtitle { text-align: center; color: #444; margin-bottom: 4px; }
        .meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin: 14px 0; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #b9c0c9; padding: 4px 6px; }
        th { background: #eef1f5; text-align: center; }
        td.num { text-align: right; }
        td.center { text-align: center; }
        tfoot td { font-weight: 700; background: #f4f6f9; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('container.show', $container->id) }}" class="btn btn-back">← Back</a>
        <button class="btn btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
    </div>

    <div class="sheet">
        <div class="title">{{ $company->company_name }}</div>
        <div class="subtitle">LOADING LIST</div>
        <div class="meta">
            <div><b>Shipment No:</b> {{ $container->shipment_no ?: $container->container_code }}</div>
            <div><b>Container No:</b> {{ $container->container_number ?: '—' }}</div>
            <div><b>Booking Ref:</b> {{ $container->booking_ref ?: '—' }}</div>
            <div><b>Shipping Line:</b> {{ $container->shipping_line ?: '—' }}</div>
        </div>

        @php $sl = 0; $tCtn = 0; $tGw = 0; $tCbm = 0; $tQty = 0; @endphp
        <table>
            <thead>
                <tr>
                    <th>SL</th><th>Mark</th><th>Goods Description</th><th>Unit</th>
                    <th>CTN Qty</th><th>Total Qty</th><th>Gross Wt</th><th>CBM</th><th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse($container->orders as $o)
                    @foreach($o->items as $item)
                        @php
                            $sl++;
                            $gw = (float) ($item->actual_weight ?: $item->net_weight);
                            $tCtn += (float) $item->package_quantity; $tGw += $gw; $tCbm += (float) $item->cbm; $tQty += (float) $item->quantity;
                        @endphp
                        <tr>
                            <td class="center">{{ $sl }}</td>
                            <td>{{ $o->shipping_mark ?: ($o->customer->name ?? '—') }}</td>
                            <td>{{ $item->item_description ?: '—' }}</td>
                            <td class="center">{{ $item->unit->short_name ?? $item->unit->name ?? '—' }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td class="num">{{ $gw ? rtrim(rtrim(number_format($gw, 2), '0'), '.') : '—' }}</td>
                            <td class="num">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="9" class="center">No orders assigned to this container.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="center">TOTAL</td>
                    <td class="num">{{ rtrim(rtrim(number_format($tCtn, 2), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($tQty, 2), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($tGw, 2), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($tCbm, 4), '0'), '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
