<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\PackingType;
use App\Models\Quotation;
use App\Models\TransportationMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index()
    {
        $quotations = Quotation::with('customer')->withCount('items')->latest()->get();

        return view('admin.backend.quotations.quotations', compact('quotations'));
    }

    public function create()
    {
        return view('admin.backend.quotations.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $quotation = Quotation::create([
                'query_received_date' => $data['query_received_date'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'submitted_to_customer' => $request->boolean('submitted_to_customer'),
                'status' => $data['status'] ?? 'draft',
                'remarks' => $data['remarks'] ?? null,
                'added_by' => Auth::id(),
            ]);

            $this->syncItems($quotation, $data['items']);
        });

        return redirect()->route('quotations.index')->with('success', 'Quotation created successfully.');
    }

    public function show($id)
    {
        $quotation = Quotation::with(['customer', 'items.category', 'items.supplier', 'items.transportationMode', 'items.packingType'])
            ->findOrFail($id);

        return view('admin.backend.quotations.show', compact('quotation'));
    }

    public function edit($id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);

        return view('admin.backend.quotations.edit', array_merge($this->formData(), compact('quotation')));
    }

    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);
        $data = $this->validated($request);

        DB::transaction(function () use ($quotation, $data, $request) {
            $quotation->update([
                'query_received_date' => $data['query_received_date'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'submitted_to_customer' => $request->boolean('submitted_to_customer'),
                'status' => $data['status'] ?? 'draft',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
        });

        return redirect()->route('quotations.index')->with('success', 'Quotation updated successfully.');
    }

    public function destroy($id)
    {
        Quotation::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Quotation deleted successfully.');
    }

    /**
     * Create the quotation's items with computed profit, then roll the totals up to the header.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Quotation $quotation, array $items): void
    {
        $grandTotal = 0;
        $totalProfit = 0;

        foreach ($items as $row) {
            $qty = (float) ($row['package_quantity'] ?? 0);
            $cost = (float) ($row['supplier_asking_price'] ?? 0);
            $sell = (float) ($row['our_asking_price'] ?? 0);

            $lineTotal = round($sell * $qty, 2);
            $unitProfit = round($sell - $cost, 2);
            $lineProfit = round($unitProfit * $qty, 2);
            $margin = $sell > 0 ? round(($unitProfit / $sell) * 100, 2) : 0;

            $quotation->items()->create([
                'category_id' => $row['category_id'] ?? null,
                'hs_code' => $row['hs_code'] ?? null,
                'transportation_mode_id' => $row['transportation_mode_id'] ?? null,
                'country_of_loading' => $row['country_of_loading'] ?? null,
                'packing_type_id' => $row['packing_type_id'] ?? null,
                'package_quantity' => $qty,
                'net_weight' => $row['net_weight'] ?? null,
                'gross_weight' => $row['gross_weight'] ?? null,
                'length' => $row['length'] ?? null,
                'width' => $row['width'] ?? null,
                'height' => $row['height'] ?? null,
                'cbm' => $row['cbm'] ?? null,
                'supplier_asking_price' => $cost,
                'supplier_id' => $row['supplier_id'] ?? null,
                'supplier_quotation_date' => $row['supplier_quotation_date'] ?? null,
                'our_asking_price' => $sell,
                'line_total' => $lineTotal,
                'unit_profit' => $unitProfit,
                'total_profit' => $lineProfit,
                'profit_margin' => $margin,
                'remarks' => $row['remarks'] ?? null,
            ]);

            $grandTotal += $lineTotal;
            $totalProfit += $lineProfit;
        }

        $quotation->update([
            'grand_total' => round($grandTotal, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_margin' => $grandTotal > 0 ? round(($totalProfit / $grandTotal) * 100, 2) : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'customers' => Contact::customers()->orderBy('name')->get(),
            'suppliers' => Contact::suppliers()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'transportationModes' => TransportationMode::orderBy('name')->get(),
            'packingTypes' => PackingType::orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'query_received_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:contacts,id',
            'status' => 'nullable|in:draft,submitted,accepted,rejected,converted',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.hs_code' => 'nullable|string|max:100',
            'items.*.transportation_mode_id' => 'nullable|exists:transportation_modes,id',
            'items.*.country_of_loading' => 'nullable|string|max:255',
            'items.*.packing_type_id' => 'nullable|exists:packing_types,id',
            'items.*.package_quantity' => 'nullable|numeric|min:0',
            'items.*.net_weight' => 'nullable|numeric|min:0',
            'items.*.gross_weight' => 'nullable|numeric|min:0',
            'items.*.length' => 'nullable|numeric|min:0',
            'items.*.width' => 'nullable|numeric|min:0',
            'items.*.height' => 'nullable|numeric|min:0',
            'items.*.cbm' => 'nullable|numeric|min:0',
            'items.*.supplier_asking_price' => 'nullable|numeric|min:0',
            'items.*.supplier_id' => 'nullable|exists:contacts,id',
            'items.*.supplier_quotation_date' => 'nullable|date',
            'items.*.our_asking_price' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);
    }
}
