<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ExchangeRateController extends Controller
{
    /**
     * The dollar rate for each day, newest first, and the one in force today.
     */
    public function index()
    {
        $rates = ExchangeRate::with('addedBy:id,first_name,last_name')
            ->orderByDesc('rate_date')
            ->paginate(50);

        // Each row is compared with the day before it, including the last on the page.
        $older = $rates->isEmpty() ? null : ExchangeRate::whereDate('rate_date', '<', $rates->last()->rate_date)
            ->orderByDesc('rate_date')
            ->first();

        return view('admin.backend.rates.exchange_rates', [
            'rates' => $rates,
            'older' => $older,
            'today' => ExchangeRate::today(),
            'current' => ExchangeRate::forDate(),
        ]);
    }

    /**
     * The rate in force on a date, for a form to fill in: that day's own, or the
     * latest before it (and which day that was). The rate is null before the first one set.
     */
    public function lookup(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => 'required|date'])['date'];
        $rate = ExchangeRate::forDate($date);

        return response()->json([
            'usd_rate' => $rate ? (float) $rate->usd_rate : null,
            'rate_date' => $rate?->rate_date->toDateString(),
            'is_exact' => $rate ? $rate->isFor($date) : false,
        ]);
    }

    /**
     * Set the rate for a day — replacing that day's rate if it already has one.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $existing = ExchangeRate::onDate($data['rate_date']);

        if ($existing && ! $request->user()->can('exchange-rates.edit')) {
            return redirect()->back()->withInput()
                ->with('error', Carbon::parse($data['rate_date'])->format('d M Y').' already has a rate; changing it needs the Edit exchange rates permission.');
        }

        $rate = $existing
            ? tap($existing)->update($data)
            : ExchangeRate::create($data + ['added_by' => Auth::id()]);

        return redirect()->route('exchange.rates')
            ->with('success', 'Dollar rate '.($existing ? 'changed' : 'set').': '.$rate->activityLabel().'.');
    }

    public function update(Request $request, $id)
    {
        $rate = ExchangeRate::findOrFail($id);
        $data = $this->validated($request);

        $clash = ExchangeRate::onDate($data['rate_date']);
        if ($clash && $clash->id !== $rate->id) {
            return redirect()->back()
                ->with('error', Carbon::parse($data['rate_date'])->format('d M Y').' already has its own rate — change that one instead.');
        }

        $rate->update($data);

        return redirect()->back()->with('success', 'Dollar rate changed: '.$rate->activityLabel().'.');
    }

    public function destroy($id)
    {
        $rate = ExchangeRate::findOrFail($id);
        $rate->delete();

        return redirect()->back()->with('success', 'Dollar rate for '.$rate->rate_date->format('d M Y').' removed.');
    }

    /**
     * @return array{rate_date: string, usd_rate: float|string, note: string|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'rate_date' => 'required|date',
            'usd_rate' => 'required|numeric|min:0.0001|max:100000',
            'note' => 'nullable|string|max:255',
        ]);

        $data['rate_date'] = Carbon::parse($data['rate_date'])->toDateString();
        $data['note'] ??= null;

        return $data;
    }
}
