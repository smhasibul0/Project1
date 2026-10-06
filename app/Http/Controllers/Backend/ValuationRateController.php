<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\HsCode;
use App\Models\ValuationRate;
use App\Support\ValuationReportParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ValuationRateController extends Controller
{
    /** Uploaded reports hold other importers' bills, so they stay out of the public folder. */
    private const DISK = 'local';

    /**
     * Every uploaded rate, newest first, with the dollar rate that turns them into taka.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $digits = HsCode::digits($search);

        $rates = ValuationRate::query()
            ->with(['hsCode:id,code_digits,description', 'addedBy:id,first_name,last_name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($match) => $match
                ->when($digits !== '', fn ($byCode) => $byCode->where('code_digits', 'like', $digits.'%'))
                ->orWhere('description', 'like', '%'.$search.'%')))
            ->latestFirst()
            ->paginate(50)
            ->withQueryString();

        // The rate each code's quotations use: its newest upload.
        $currentIds = ValuationRate::query()
            ->whereIn('code_digits', $rates->pluck('code_digits')->unique())
            ->latestFirst()
            ->get(['id', 'code_digits'])
            ->unique('code_digits')
            ->pluck('id')
            ->all();

        return view('admin.backend.rates.index', [
            'rates' => $rates,
            'currentIds' => $currentIds,
            'search' => $search,
            'dollarRate' => ExchangeRate::forDate(),
        ]);
    }

    /**
     * One upload: its bills of entry, as read from the report.
     */
    public function show($id)
    {
        $rate = ValuationRate::with(['hsCode', 'addedBy'])->findOrFail($id);

        return view('admin.backend.rates.show', [
            'rate' => $rate,
            'dollarRate' => ExchangeRate::forDate(),
            'isCurrent' => $rate->isCurrent(),
        ]);
    }

    /**
     * Read one or more Customs valuation reports and store a rate per HS code in
     * each, under the date given.
     */
    public function store(Request $request, ValuationReportParser $parser)
    {
        $data = $request->validate([
            'rate_date' => 'required|date',
            'reports' => 'required|array|max:20',
            'reports.*' => 'file|mimes:pdf|max:10240',
        ]);

        $stored = [];
        $failed = [];

        foreach ($request->file('reports') as $file) {
            $name = $file->getClientOriginalName();

            try {
                $parsed = $parser->parse($file->getRealPath());
            } catch (\RuntimeException $e) {
                $failed[] = $name.': '.$e->getMessage();

                continue;
            }

            $path = $file->store('valuation-reports', self::DISK);

            foreach ($parsed as $code) {
                $rate = ValuationRate::create([
                    'rate_date' => $data['rate_date'],
                    'code_digits' => $code['code_digits'],
                    'hs_code' => ValuationRate::formatCode($code['code_digits']),
                    'description' => $code['description'],
                    'unit_price' => $code['unit_price'],
                    'bills_count' => count($code['bills']),
                    'bills' => $code['bills'],
                    'period_from' => $code['period_from'],
                    'period_to' => $code['period_to'],
                    'file_path' => $path,
                    'original_name' => $name,
                    'added_by' => Auth::id(),
                ]);

                $stored[] = $rate->hs_code.' at '.rtrim(rtrim(number_format((float) $rate->unit_price, 4), '0'), '.')
                    .' USD/kg ('.$rate->bills_count.' bills)';
            }
        }

        $redirect = redirect()->route('rates.index');

        if ($stored !== []) {
            $redirect->with('success', 'Rates saved: '.implode('; ', $stored).'.');
        }

        if ($failed !== []) {
            $redirect->with('error', 'Not read — '.implode(' · ', $failed));
        }

        return $redirect;
    }

    /**
     * The report as uploaded.
     */
    public function pdf($id)
    {
        $rate = ValuationRate::findOrFail($id);

        abort_unless($rate->file_path && Storage::disk(self::DISK)->exists($rate->file_path), 404);

        return Storage::disk(self::DISK)->response($rate->file_path, $rate->original_name ?: 'valuation-report.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy($id)
    {
        $rate = ValuationRate::findOrFail($id);
        $path = $rate->file_path;
        $rate->delete();

        // A report covering several codes stays until the last of its rates goes.
        if ($path && ! ValuationRate::where('file_path', $path)->exists()) {
            Storage::disk(self::DISK)->delete($path);
        }

        return redirect()->back()->with('success', 'Rate deleted.');
    }
}
