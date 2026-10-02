<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\CostCategory;
use App\Models\PackingType;
use App\Models\Quotation;
use App\Models\TransportationMode;
use App\Support\DutyCalculator;
use App\Support\NumberToWords;
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
        $data = $this->validated($request);
        $send = $request->input('action') === 'send';
        $packingList = $this->storePackingList($request);

        DB::transaction(function () use ($data, $send, $packingList) {
            $quotation = Quotation::create([
                'query_received_date' => $data['query_received_date'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'transportation_mode_id' => $data['transportation_mode_id'] ?? null,
                'country_of_loading' => $data['country_of_loading'] ?? null,
                'submitted_to_customer' => $send,
                'status' => $send ? 'quoted' : 'draft',
                'remarks' => $data['remarks'] ?? null,
                'packing_list_path' => $packingList,
                'added_by' => Auth::id(),
            ]);

            $this->syncExpenses($quotation, $data['expenses'] ?? []);
            $this->applyPricing($quotation, $data);
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
        $quotation = Quotation::with(['customer', 'transportationMode', 'items.hsCodeRecord', 'items.packingType', 'expenses.category'])
            ->findOrFail($id);

        return view('admin.backend.quotations.show', compact('quotation'));
    }

    /**
     * The customer's copy: items, the rate per CBM and what they pay. None of our
     * cost side — freight, duty, additional costs and profit stay off this page.
     */
    public function print($id)
    {
        $quotation = Quotation::with(['customer', 'transportationMode', 'items.packingType'])
            ->findOrFail($id);
        $company = CompanySetting::current();
        $currency = $company->currency ?: 'BDT';

        return view('admin.backend.quotations.print', [
            'quotation' => $quotation,
            'company' => $company,
            'amountWords' => NumberToWords::make((float) $quotation->customer_charge, $currency === 'BDT' ? 'Taka' : $currency),
        ]);
    }

    public function edit($id)
    {
        $quotation = Quotation::with(['items', 'expenses'])->findOrFail($id);

        return view('admin.backend.quotations.edit', array_merge($this->formData(), compact('quotation')));
    }

    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);
        $data = $this->validated($request);
        $send = $request->input('action') === 'send';
        $packingList = $this->storePackingList($request);
        $before = $this->contents($quotation);

        DB::transaction(function () use ($quotation, $data, $send, $packingList) {
            $quotation->update([
                'query_received_date' => $data['query_received_date'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'transportation_mode_id' => $data['transportation_mode_id'] ?? null,
                'country_of_loading' => $data['country_of_loading'] ?? null,
                // "Send to Customer" quotes it; otherwise keep its current status (draft save).
                'submitted_to_customer' => $send ? true : $quotation->submitted_to_customer,
                'status' => $send ? 'quoted' : $quotation->status,
                'remarks' => $data['remarks'] ?? null,
                'packing_list_path' => $packingList ?? $quotation->packing_list_path,
            ]);

            $quotation->items()->delete();
            $quotation->expenses()->delete();
            $this->syncExpenses($quotation, $data['expenses'] ?? []);
            $this->applyPricing($quotation, $data);
        });

        $quotation->logChangedContents($before, $this->contents($quotation));

        return redirect()->route($send ? 'quotations.index' : 'quotation.show', $send ? [] : $quotation->id)
            ->with('success', $send ? 'Quotation sent to the customer.' : 'Quotation saved.');
    }

    /**
     * @return array<string, string>
     */
    private function contents(Quotation $quotation): array
    {
        return [
            'items' => Quotation::activityFingerprint($quotation->items()),
            'expenses' => Quotation::activityFingerprint($quotation->expenses()),
        ];
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
     * Write the item lines and the money that follows from them.
     *
     * Freight is bought and sold by the cubic metre at one rate for the whole
     * shipment, so each line carries its share of the freight cost (split by CBM)
     * and its share of the charge, on top of the duty its HS code attracts.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function applyPricing(Quotation $quotation, array $data): void
    {
        $items = $data['items'];
        $totalCbm = round(array_sum(array_map(fn (array $row) => (float) ($row['cbm'] ?? 0), $items)), 4);
        $sellRate = (float) ($data['sell_rate_per_cbm'] ?? 0);

        $freight = $this->freightAttributes($data, $totalCbm);
        $freightCost = (float) $freight['freight_amount'];
        $customerCharge = round($sellRate * $totalCbm, 2);

        $totalDuty = 0.0;

        foreach ($items as $row) {
            $cbm = (float) ($row['cbm'] ?? 0);
            $declaredValue = (float) ($row['declared_value'] ?? 0);

            // Duty is assessed on the declared invoice value plus the landing charge.
            $assessableValue = ($row['assessable_value'] ?? '') !== '' && $row['assessable_value'] !== null
                ? (float) $row['assessable_value']
                : DutyCalculator::defaultAssessableValue($declaredValue);
            $duty = DutyCalculator::calculate($assessableValue, $row);

            $lineCharge = round($sellRate * $cbm, 2);
            $lineFreight = $totalCbm > 0 ? round($freightCost * $cbm / $totalCbm, 2) : 0.0;

            $quotation->items()->create([
                'hs_code_id' => $row['hs_code_id'] ?? null,
                'hs_code' => $row['hs_code'] ?? null,
                'description' => $row['description'] ?? null,
                'packing_type_id' => $row['packing_type_id'] ?? null,
                'package_quantity' => (float) ($row['package_quantity'] ?? 0),
                'net_weight' => $row['net_weight'] ?? null,
                'gross_weight' => $row['gross_weight'] ?? null,
                'length' => $row['length'] ?? null,
                'width' => $row['width'] ?? null,
                'height' => $row['height'] ?? null,
                'cbm' => $cbm,
                'declared_value' => $declaredValue,
                'assessable_value' => $assessableValue,
                'cd_rate' => (float) ($row['cd_rate'] ?? 0),
                'rd_rate' => (float) ($row['rd_rate'] ?? 0),
                'sd_rate' => (float) ($row['sd_rate'] ?? 0),
                'vat_rate' => (float) ($row['vat_rate'] ?? 0),
                'ait_rate' => (float) ($row['ait_rate'] ?? 0),
                'at_rate' => (float) ($row['at_rate'] ?? 0),
                'duty_amount' => $duty['total'],
                'line_total' => $lineCharge,
                'total_profit' => round($lineCharge - $duty['total'] - $lineFreight, 2),
                'remarks' => $row['remarks'] ?? null,
            ]);

            $totalDuty += $duty['total'];
        }

        $expenseTotal = (float) $quotation->expenses()->sum('amount');
        $projectedCost = round($freightCost + $totalDuty + $expenseTotal, 2);
        $projectedProfit = round($customerCharge - $projectedCost, 2);

        $quotation->update($freight + [
            'sell_rate_per_cbm' => $sellRate,
            'total_cbm' => $totalCbm,
            'customer_charge' => $customerCharge,
            'grand_total' => $customerCharge,
            'total_duty' => round($totalDuty, 2),
            'projected_cost_total' => $projectedCost,
            'projected_profit' => $projectedProfit,
            'total_profit' => $projectedProfit,
            'profit_margin' => $customerCharge > 0 ? round(($projectedProfit / $customerCharge) * 100, 2) : 0,
        ]);
    }

    /**
     * Create the additional predicted cost rows.
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
                'cost_category_id' => $row['cost_category_id'] ?? null,
                'title' => $row['title'],
                'amount' => (float) ($row['amount'] ?? 0),
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    /**
     * What the shipment costs us to move: LCL buys space per CBM, FCL books a
     * whole container at a flat price.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function freightAttributes(array $data, float $totalCbm): array
    {
        $type = $data['freight_type'] ?? null;

        if ($type === 'lcl') {
            $rate = (float) ($data['freight_rate'] ?? 0);

            return [
                'freight_type' => 'lcl',
                'freight_rate' => $rate,
                'freight_container_size' => null,
                'freight_amount' => round($rate * $totalCbm, 2),
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
            'transportationModes' => TransportationMode::orderBy('name')->get(),
            'packingTypes' => PackingType::orderBy('name')->get(),
            'costCategories' => CostCategory::orderBy('name')->get(),
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
            'transportation_mode_id' => 'nullable|exists:transportation_modes,id',
            'country_of_loading' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'packing_list' => 'nullable|file|mimes:xlsx,xls|max:8192',
            'freight_type' => 'nullable|in:lcl,fcl',
            'freight_rate' => 'nullable|numeric|min:0',
            'freight_container_size' => 'nullable|string|max:20',
            'freight_amount' => 'nullable|numeric|min:0',
            'sell_rate_per_cbm' => 'nullable|numeric|min:0',
            'expenses' => 'nullable|array',
            'expenses.*.cost_category_id' => 'nullable|exists:cost_categories,id',
            'expenses.*.title' => 'nullable|string|max:255',
            'expenses.*.amount' => 'nullable|numeric|min:0',
            'expenses.*.note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.hs_code_id' => 'nullable|exists:hs_codes,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.hs_code' => 'nullable|string|max:100',
            'items.*.packing_type_id' => 'nullable|exists:packing_types,id',
            'items.*.package_quantity' => 'nullable|numeric|min:0',
            'items.*.net_weight' => 'nullable|numeric|min:0',
            'items.*.gross_weight' => 'nullable|numeric|min:0',
            'items.*.length' => 'nullable|numeric|min:0',
            'items.*.width' => 'nullable|numeric|min:0',
            'items.*.height' => 'nullable|numeric|min:0',
            'items.*.cbm' => 'nullable|numeric|min:0',
            'items.*.declared_value' => 'nullable|numeric|min:0',
            'items.*.assessable_value' => 'nullable|numeric|min:0',
            'items.*.cd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.rd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.sd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.vat_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.ait_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.at_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.remarks' => 'nullable|string',
        ]);
    }
}
