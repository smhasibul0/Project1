<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\OfficeCostType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OfficeCostTypeController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->input('q');
        $nature = (string) $request->input('nature');

        if (! in_array($nature, OfficeCostType::NATURES, true)) {
            $nature = '';
        }

        $types = OfficeCostType::with('category.parent')->withCount('expenses')
            ->search($search)
            ->when($nature !== '', fn ($query) => $query->where('nature', $nature))
            ->orderBy('nature')->orderBy('name')->get();

        return view('admin.backend.office.cost_types', [
            'fixedTypes' => $types->where('nature', 'fixed'),
            'variableTypes' => $types->where('nature', 'variable'),
            'search' => $search,
            'nature' => $nature,
            'natures' => OfficeCostType::NATURES,
            'categories' => ExpenseCategory::with('children')->whereNull('parent_id')
                ->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'nature' => ['required', Rule::in(OfficeCostType::NATURES)],
            'monthly_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        OfficeCostType::create([
            'name' => $data['name'],
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'nature' => $data['nature'],
            'monthly_amount' => $this->monthlyAmountFor($data),
            'is_active' => $request->boolean('is_active', true),
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Cost type added.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'nature' => ['required', Rule::in(OfficeCostType::NATURES)],
            'monthly_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        OfficeCostType::findOrFail($id)->update([
            'name' => $data['name'],
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'nature' => $data['nature'],
            'monthly_amount' => $this->monthlyAmountFor($data),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Cost type updated.');
    }

    /**
     * Only fixed types carry a standard monthly amount — switching one to
     * variable clears it, so it can never be picked up by generation.
     *
     * @param  array<string, mixed>  $data
     */
    private function monthlyAmountFor(array $data): ?float
    {
        if ($data['nature'] !== 'fixed' || ($data['monthly_amount'] ?? '') === '' || $data['monthly_amount'] === null) {
            return null;
        }

        return (float) $data['monthly_amount'];
    }

    /**
     * Deleting a type leaves its expenses in place (the FK nulls out), so
     * historical months keep their totals.
     */
    public function destroy($id)
    {
        OfficeCostType::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Cost type deleted.');
    }
}
