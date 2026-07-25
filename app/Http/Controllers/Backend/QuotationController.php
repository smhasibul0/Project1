<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\CostCategory;
use App\Models\PackingType;
use App\Models\Quotation;
use App\Models\TransportationMode;
use App\Support\DutyCalculator;
use App\Support\PackingListParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index()
    {
        // Pending customer requests live on their own page until they're quoted.
        $quotations = Quotation::with('customer')->withCount('items')
            ->where('status', '!=', 'requested')->latest()->get();

        return view('admin.backend.quotations.quotations', compact('quotations'));
    }

    /**
     * Pending quotation requests submitted by customers from the portal.
     */
    public function requests()
    {
        $quotations = Quotation::with('customer')->withCount('items')
            ->where('status', 'requested')->latest()->get();

        return view('admin.backend.quotations.requests', compact('quotations'));
    }

    public function create()
    {
        return view('admin.backend.quotations.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, requirePackingList: true);
        $send = $request->input('action') === 'send';
        $packingList = $this->storePackingList($request);

        DB::transaction(function () use ($data, $send, $packingList) {
            $quotation = Quotation::create([
                'query_received_date' => $data['query_received_date'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'submitted_to_customer' => $send,
                'status' => $send ? 'quoted' : 'draft',
                'remarks' => $data['remarks'] ?? null,
                'packing_list_path' => $packingList,
                'added_by' => Auth::id(),
            ] + $this->freightAttributes($data));

            $this->syncItems($quotation, $data['items']);
            $this->syncExpenses($quotation, $data['expenses'] ?? []);
            $this->updateProjection($quotation);
        });

        return redirect()->route('quotations.index')
            ->with('success', $send ? 'Quotation sent to the customer.' : 'Quotation saved as draft.');
    }

    /**
     * Parse an uploaded packing list workbook into quotation item rows (AJAX).
     */
    public function parsePackingList(Request $request, PackingListParser $parser): JsonResponse
    {
        $request->validate([
            'packing_list' => 'required|file|mimes:xlsx,xls|max:8192',
        ]);

        try {
            return response()->json($parser->parse($request->file('packing_list')->getRealPath()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show($id)
    {
        $quotation = Quotation::with(['customer', 'items.product', 'items.category', 'items.supplier', 'items.transportationMode', 'items.packingType', 'expenses.category'])
            ->findOrFail($id);

        return view('admin.backend.quotations.show', compact('quotation'));
    }

    public function edit($id)
    {
        $quotation = Quotation::with(['items', 'expenses'])->findOrFail($id);

        return view('admin.backend.quotations.edit', array_merge($this->formData(), compact('quotation')));
    }

    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);
        // Legacy quotations without an attachment must supply one on their next save.
        $data = $this->validated($request, requirePackingList: empty($quotation->packing_list_path));
        $send = $request->input('action') === 'send';
        $packingList = $this->storePackingList($request);

        DB::transaction(function () use ($quotation, $data, $send, $packingList) {
            $quotation->update([
                'query_received_date' => $data['query_received_date'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                // "Send to Customer" quotes it; otherwise keep its current status (draft save).
                'submitted_to_customer' => $send ? true : $quotation->submitted_to_customer,
                'status' => $send ? 'quoted' : $quotation->status,
                'remarks' => $data['remarks'] ?? null,
                'packing_list_path' => $packingList ?? $quotation->packing_list_path,
            ] + $this->freightAttributes($data));

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $quotation->expenses()->delete();
            $this->syncExpenses($quotation, $data['expenses'] ?? []);
            $this->updateProjection($quotation);
        });

        return redirect()->route($send ? 'quotations.index' : 'quotation.show', $send ? [] : $quotation->id)
            ->with('success', $send ? 'Quotation sent to the customer.' : 'Quotation saved.');
    }

    /**
     * Admin denies a quotation (e.g. after a customer asks to negotiate).
     */
    public function deny($id)
    {
        Quotation::findOrFail($id)->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Quotation rejected.');
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

            // Duty projection: assessable value defaults to goods cost + landing charge.
            $assessableValue = ($row['assessable_value'] ?? '') !== '' && $row['assessable_value'] !== null
                ? (float) $row['assessable_value']
                : DutyCalculator::defaultAssessableValue($cost * $qty);
            $duty = DutyCalculator::calculate($assessableValue, $row);

            $quotation->items()->create([
                'product_id' => $row['product_id'] ?? null,
                'description' => $row['description'] ?? null,
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
                'assessable_value' => $assessableValue,
                'cd_rate' => (float) ($row['cd_rate'] ?? 0),
                'rd_rate' => (float) ($row['rd_rate'] ?? 0),
                'sd_rate' => (float) ($row['sd_rate'] ?? 0),
                'vat_rate' => (float) ($row['vat_rate'] ?? 0),
                'ait_rate' => (float) ($row['ait_rate'] ?? 0),
                'at_rate' => (float) ($row['at_rate'] ?? 0),
                'duty_amount' => $duty['total'],
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
     * Create the predicted LC / custom expense rows.
     *
     * @param  array<int, array<string, mixed>>  $expenses
     */
    private function syncExpenses(Quotation $quotation, array $expenses): void
    {
        foreach ($expenses as $row) {
            if (($row['title'] ?? '') === '' && (float) ($row['amount'] ?? 0) <= 0) {
                continue;
            }

            $quotation->expenses()->create([
                'expense_group' => $row['expense_group'] ?? 'custom',
                'cost_category_id' => $row['cost_category_id'] ?? null,
                'title' => $row['title'],
                'amount' => (float) ($row['amount'] ?? 0),
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    /**
     * Predicted freight for the projection: LCL is priced per CBM of the quoted
     * items, FCL is a flat container price entered by the user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function freightAttributes(array $data): array
    {
        $type = $data['freight_type'] ?? null;

        if ($type === 'lcl') {
            $rate = (float) ($data['freight_rate'] ?? 0);
            $cbm = array_sum(array_map(fn (array $row) => (float) ($row['cbm'] ?? 0), $data['items']));

            return [
                'freight_type' => 'lcl',
                'freight_rate' => $rate,
                'freight_container_size' => null,
                'freight_amount' => round($rate * $cbm, 2),
            ];
        }

        if ($type === 'fcl') {
            return [
                'freight_type' => 'fcl',
                'freight_rate' => null,
                'freight_container_size' => $data['freight_container_size'] ?? null,
                'freight_amount' => round((float) ($data['freight_amount'] ?? 0), 2),
            ];
        }

        return [
            'freight_type' => null,
            'freight_rate' => null,
            'freight_container_size' => null,
            'freight_amount' => 0,
        ];
    }

    /**
     * Roll duties, freight + predicted expenses into the quotation's projected cost & profit.
     */
    private function updateProjection(Quotation $quotation): void
    {
        $goodsCost = (float) $quotation->items()
            ->selectRaw('COALESCE(SUM(supplier_asking_price * package_quantity), 0) AS goods')
            ->value('goods');
        $totalDuty = (float) $quotation->items()->sum('duty_amount');
        $expenseTotal = (float) $quotation->expenses()->sum('amount');

        $projectedCost = round($goodsCost + $totalDuty + $expenseTotal + (float) $quotation->freight_amount, 2);

        $quotation->update([
            'total_duty' => round($totalDuty, 2),
            'projected_cost_total' => $projectedCost,
            'projected_profit' => round((float) $quotation->grand_total - $projectedCost, 2),
        ]);
    }

    /**
     * Move the uploaded packing list into public/upload/quotation, returning the filename.
     */
    private function storePackingList(Request $request): ?string
    {
        if (! $request->hasFile('packing_list')) {
            return null;
        }

        $file = $request->file('packing_list');
        $filename = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/quotation');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);

        return $filename;
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
            'costCategories' => CostCategory::orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requirePackingList = false): array
    {
        return $request->validate([
            'query_received_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:contacts,id',
            'remarks' => 'nullable|string',
            'packing_list' => ($requirePackingList ? 'required' : 'nullable').'|file|mimes:xlsx,xls|max:8192',
            'freight_type' => 'nullable|in:lcl,fcl',
            'freight_rate' => 'nullable|numeric|min:0',
            'freight_container_size' => 'nullable|string|max:20',
            'freight_amount' => 'nullable|numeric|min:0',
            'expenses' => 'nullable|array',
            'expenses.*.expense_group' => 'required|in:lc,custom',
            'expenses.*.cost_category_id' => 'nullable|exists:cost_categories,id',
            'expenses.*.title' => 'nullable|string|max:255',
            'expenses.*.amount' => 'nullable|numeric|min:0',
            'expenses.*.note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
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
            'items.*.assessable_value' => 'nullable|numeric|min:0',
            'items.*.cd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.rd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.sd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.vat_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.ait_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.at_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.our_asking_price' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);
    }
}
