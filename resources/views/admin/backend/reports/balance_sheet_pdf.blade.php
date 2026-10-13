<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Balance Sheet {{ $date->format('d M Y') }}</title>
    @php
        $accent = $company->primary_color ?: '#1e40af';
        $money = fn ($value) => '৳ '.number_format((float) $value, 2);
        $printedBy = auth()->user() ? (trim((string) auth()->user()->name) ?: auth()->user()->activityLabel()) : null;
    @endphp
    {{-- Rendered only as a PDF (App\Support\PdfDocument): tables, no flexbox. --}}
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; font-size: 10.5px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .top td { vertical-align: top; padding: 0; }
        .company-name { font-size: 20px; font-weight: 800; color: {{ $accent }}; }
        .company-meta { color: #444; margin-top: 4px; line-height: 1.4; }
        .doc-word { font-size: 22px; font-weight: 800; color: #9aa3af; text-align: right; }
        .as-of { text-align: right; margin-top: 4px; font-size: 11px; }
        .summary { margin-top: 16px; }
        .summary td { border: 1px solid #b9c0c9; padding: 7px 8px; width: 25%; }
        .summary .label { color: #555; font-size: 9.5px; display: block; }
        .summary .value { font-size: 13px; font-weight: 700; }
        h3 { font-size: 12px; margin: 18px 0 6px; color: {{ $accent }}; text-transform: uppercase; letter-spacing: .5px; }
        .lines th, .lines td { border: 1px solid #b9c0c9; padding: 5px 7px; }
        .lines th { background: #eef1f5; text-align: left; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines .group td { background: #f6f7f9; font-weight: 700; }
        .lines .total td { font-weight: 700; background: #eef1f5; }
        .lines .sub { color: #666; font-size: 9px; }
        .lines .sub .taka-sign { margin-left: 2px; }
        .net td { padding: 8px; font-size: 12px; font-weight: 700; background: {{ $accent }}; color: #fff; }
        .footer { margin-top: 18px; color: #777; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td style="width: 60%">
                <div class="company-name">{{ $company->company_name }}</div>
                <div class="company-meta">{{ $company->address }}</div>
            </td>
            <td>
                <div class="doc-word">BALANCE SHEET</div>
                <div class="as-of">At the end of <strong>{{ $date->format('d M Y') }}</strong>{{ $isToday ? ' (today, so far)' : '' }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td><span class="label">Total assets</span><span class="value">{{ $money($total) }}</span></td>
            <td><span class="label">Money borrowed</span><span class="value">{{ $money($loansPayable) }}</span></td>
            <td><span class="label">Net worth</span><span class="value">{{ $money($netWorth) }}</span></td>
            <td><span class="label">Receivables</span><span class="value">{{ $money($receivables) }}</span></td>
        </tr>
    </table>

    <h3>Assets</h3>
    <table class="lines">
        <thead><tr><th>Cash &amp; bank</th><th>Type</th><th class="num">Balance</th></tr></thead>
        <tbody>
            @forelse($grouped as $type => $typeAccounts)
            <tr class="group"><td colspan="2">{{ $type }}</td><td class="num">{{ $money($typeAccounts->sum(fn ($a) => $a->balance_as_of + $a->usd_cost_as_of)) }}</td></tr>
            @foreach($typeAccounts as $a)
            <tr>
                <td>{{ $a->name }}</td>
                <td>{{ $a->accountType->name ?? '—' }}</td>
                <td class="num">
                    {{ $money($a->balance_as_of + $a->usd_cost_as_of) }}
                    @if($a->usd_as_of > 0)<div class="sub">{{ $money($a->balance_as_of) }} + ${{ number_format($a->usd_as_of, 2) }} ({{ $money($a->usd_cost_as_of) }} at cost)</div>@endif
                </td>
            </tr>
            @endforeach
            @empty
            <tr><td colspan="3">No accounts open on this day.</td></tr>
            @endforelse
            <tr class="total"><td colspan="2">Cash &amp; bank total{{ $dollarsHeld > 0 ? ' (incl. $'.number_format($dollarsHeld, 2).' at cost)' : '' }}</td><td class="num">{{ $money($cashBank) }}</td></tr>
            <tr><td colspan="2">Receivables — what customers owed on their orders</td><td class="num">{{ $money($receivables) }}</td></tr>
            <tr><td colspan="2">Money lent out — still to collect</td><td class="num">{{ $money($loansReceivable) }}</td></tr>
            <tr class="total"><td colspan="2">Total assets</td><td class="num">{{ $money($total) }}</td></tr>
        </tbody>
    </table>

    @if($lendings->isNotEmpty())
    <h3>Money lent out</h3>
    <table class="lines">
        <thead><tr><th>Borrower</th><th>Loan</th><th>Due</th><th class="num">Outstanding</th></tr></thead>
        <tbody>
            @foreach($lendings as $loan)
            <tr><td>{{ $loan->counterparty }}</td><td>{{ $loan->loan_code }}</td><td>{{ $loan->due_date?->format('d M Y') ?? '—' }}</td><td class="num">{{ $money($loan->outstanding_as_of) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <h3>Liabilities</h3>
    <table class="lines">
        <thead><tr><th>Lender</th><th>Loan</th><th>Due</th><th class="num">Outstanding</th></tr></thead>
        <tbody>
            @forelse($borrowings as $loan)
            <tr><td>{{ $loan->counterparty }}</td><td>{{ $loan->loan_code }}</td><td>{{ $loan->due_date?->format('d M Y') ?? '—' }}</td><td class="num">{{ $money($loan->outstanding_as_of) }}</td></tr>
            @empty
            <tr><td colspan="4">Nothing borrowed.</td></tr>
            @endforelse
            <tr class="total"><td colspan="3">Total borrowed</td><td class="num">{{ $money($loansPayable) }}</td></tr>
        </tbody>
    </table>

    <table class="net" style="margin-top: 14px">
        <tr><td>Net worth (assets less borrowings)</td><td style="text-align:right">{{ $money($netWorth) }}</td></tr>
    </table>

    <div class="footer">Printed {{ now(config('app.display_timezone'))->format('d M Y, h:i A') }}{{ $printedBy ? ' by '.$printedBy : '' }}</div>
</body>
</html>
