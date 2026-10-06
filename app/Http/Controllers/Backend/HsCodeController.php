<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HsCode;
use App\Support\HsCodeSheetImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HsCodeController extends Controller
{
    /**
     * The tariff book runs to several thousand lines, so the list is searched
     * and paged on the server rather than handed to the client table.
     */
    public function index(Request $request)
    {
        $hsCodes = HsCode::search($request->input('q'))
            ->orderBy('code_digits')
            ->paginate(50)
            ->withQueryString();

        return view('admin.backend.hs_codes.hs_codes', [
            'hsCodes' => $hsCodes,
            'search' => (string) $request->input('q'),
            'total' => HsCode::count(),
        ]);
    }

    /**
     * Load a customs tariff workbook, or a rates sheet that tops up what's stored.
     */
    public function import(Request $request, HsCodeSheetImporter $importer)
    {
        $request->validate([
            'sheet' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            $stats = $importer->import($request->file('sheet')->getRealPath());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $message = $stats['mode'] === 'merge'
            ? "Rates merged: {$stats['updated']} HS code(s) updated, {$stats['skipped']} not found."
            : "Tariff imported: {$stats['created']} new HS code(s), {$stats['updated']} updated.";

        ActivityLog::recordBulk(HsCode::class, ActivityLog::IMPORTED, $request->file('sheet')->getClientOriginalName(), $message);

        return redirect()->back()->with('success', $message.' Columns read: '.implode(', ', $stats['columns']).'.');
    }

    /**
     * Type-ahead used by the quotation and order item rows.
     */
    public function search(Request $request): JsonResponse
    {
        // The reference declared value is for whoever prices quotations — the
        // customer portal uses this lookup too, and never sees it.
        $withReference = $request->user()->can('quotations.create') || $request->user()->can('quotations.edit');

        $results = HsCode::search($request->input('q'))
            ->where('is_active', true)
            ->when($withReference, fn ($q) => $q->with('latestValuationRate'))
            ->orderBy('code_digits')
            ->limit(20)
            ->get(['id', 'code', 'code_digits', 'description', 'statistical_unit', ...HsCode::RATE_FIELDS]);

        return response()->json($results->map(function (HsCode $hsCode) use ($withReference) {
            $row = $hsCode->only(['id', 'code', 'description', 'statistical_unit', ...HsCode::RATE_FIELDS]);
            $rate = $withReference ? $hsCode->latestValuationRate : null;

            if ($withReference) {
                $row['reference'] = $rate ? [
                    'unit_price' => (float) $rate->unit_price,
                    'rate_date' => $rate->rate_date->toDateString(),
                    'options' => $rate->suggestions(),
                ] : null;
            }

            return $row;
        }));
    }

    public function store(Request $request)
    {
        HsCode::create($this->validated($request));

        return redirect()->back()->with('success', 'HS code added successfully.');
    }

    public function update(Request $request, $id)
    {
        HsCode::findOrFail($id)->update($this->validated($request, $id));

        return redirect()->back()->with('success', 'HS code updated successfully.');
    }

    public function destroy($id)
    {
        HsCode::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'HS code deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $id = null): array
    {
        $unique = 'unique:hs_codes,code_digits'.($id ? ','.$id : '');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'description' => 'required|string',
            'statistical_unit' => 'nullable|string|max:30',
            'cd_rate' => 'nullable|numeric|min:0|max:1000',
            'sd_rate' => 'nullable|numeric|min:0|max:1000',
            'vat_rate' => 'nullable|numeric|min:0|max:1000',
            'ait_rate' => 'nullable|numeric|min:0|max:1000',
            'rd_rate' => 'nullable|numeric|min:0|max:1000',
            'at_rate' => 'nullable|numeric|min:0|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        // The digits are the identity, so uniqueness is checked against them.
        validator(
            ['code_digits' => HsCode::digits($data['code'])],
            ['code_digits' => ['required', $unique]],
            ['code_digits.unique' => 'That HS code already exists.']
        )->validate();

        foreach (HsCode::RATE_FIELDS as $field) {
            $data[$field] = (float) ($data[$field] ?? 0);
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
