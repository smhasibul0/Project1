<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    /**
     * The fixed asset register: what the company owns, what it cost, what has
     * been written off and what it is still carried at.
     */
    public function index()
    {
        $assets = Asset::with(['category', 'depreciations'])
            ->orderBy('asset_code')
            ->get();

        $onBooks = $assets->where('status', 'in_use');
        $sum = fn ($rows, $field) => round($rows->sum(fn (Asset $a) => (float) $a->$field), 2);

        $cost = $sum($onBooks, 'purchase_cost');
        $accumulated = round($onBooks->sum(fn (Asset $a) => $a->accumulatedDepreciation()), 2);

        return view('admin.backend.assets.assets', [
            'assets' => $assets,
            'totalCost' => $cost,
            'accumulated' => $accumulated,
            'bookValue' => round($cost - $accumulated, 2),
            'onBooksCount' => $onBooks->count(),
            'disposedCount' => $assets->where('status', '!=', 'in_use')->count(),
            'disposedProceeds' => $sum($assets->where('status', 'disposed'), 'disposal_amount'),
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(),
            'methods' => Asset::METHODS,
            'statuses' => Asset::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Asset::create($data + [
            'asset_code' => Asset::nextCode(),
            'attachment' => $this->storeAttachment($request),
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Asset added to the register.');
    }

    /**
     * Edit an asset's own details. The cost can never drop below what has
     * already been written off, which would leave it carried below salvage.
     */
    public function update(Request $request, $id)
    {
        $asset = Asset::with('depreciations')->findOrFail($id);
        $data = $this->validated($request);

        if ((float) $data['purchase_cost'] + 0.005 < $asset->accumulatedDepreciation()) {
            throw ValidationException::withMessages([
                'purchase_cost' => 'Cost cannot be less than the '.number_format($asset->accumulatedDepreciation(), 2).' already depreciated. Reverse a month first.',
            ]);
        }

        if ($attachment = $this->storeAttachment($request)) {
            $data['attachment'] = $attachment;
        }

        $asset->update($data);

        return redirect()->back()->with('success', 'Asset updated.');
    }

    /**
     * Take an asset off the books. It stops depreciating from here, and the
     * months already charged stay as they are.
     */
    public function dispose(Request $request, $id)
    {
        $asset = Asset::with('depreciations')->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['disposed', 'written_off'])],
            'disposed_on' => 'required|date',
            'disposal_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $asset->update([
            'status' => $data['status'],
            'disposed_on' => $data['disposed_on'],
            'disposal_amount' => $data['status'] === 'disposed' ? ($data['disposal_amount'] ?? 0) : null,
            'note' => $data['note'] ?? $asset->note,
        ]);

        return redirect()->back()->with('success', 'Asset marked as '.Asset::STATUSES[$data['status']].'.');
    }

    /**
     * Put a disposed asset back on the books — for a disposal recorded by
     * mistake.
     */
    public function restore($id)
    {
        Asset::findOrFail($id)->update([
            'status' => 'in_use',
            'disposed_on' => null,
            'disposal_amount' => null,
        ]);

        return redirect()->back()->with('success', 'Asset is back on the books.');
    }

    /**
     * Remove an asset and every depreciation month posted against it.
     */
    public function destroy($id)
    {
        Asset::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Asset deleted.');
    }

    /**
     * An asset needs the basis its depreciation is charged on: a useful life
     * for straight line, an annual rate for reducing balance, and neither
     * when it does not depreciate at all.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'nullable|numeric|min:0|lt:purchase_cost',
            'depreciation_method' => ['required', Rule::in(array_keys(Asset::METHODS))],
            'useful_life_years' => 'nullable|required_if:depreciation_method,straight_line|integer|min:1|max:100',
            'depreciation_rate' => 'nullable|required_if:depreciation_method,reducing_balance|numeric|min:0.01|max:100',
            'serial_no' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        return [
            'name' => $data['name'],
            'asset_category_id' => $data['asset_category_id'] ?? null,
            'purchase_date' => $data['purchase_date'],
            'purchase_cost' => $data['purchase_cost'],
            'salvage_value' => $data['salvage_value'] ?? 0,
            'depreciation_method' => $data['depreciation_method'],
            'useful_life_years' => $data['depreciation_method'] === 'straight_line' ? $data['useful_life_years'] : null,
            'depreciation_rate' => $data['depreciation_method'] === 'reducing_balance' ? $data['depreciation_rate'] : null,
            'serial_no' => $data['serial_no'] ?? null,
            'location' => $data['location'] ?? null,
            'supplier' => $data['supplier'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }

    /**
     * Move an uploaded invoice or warranty into place, returning its filename.
     */
    private function storeAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/assets');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        return $name;
    }
}
