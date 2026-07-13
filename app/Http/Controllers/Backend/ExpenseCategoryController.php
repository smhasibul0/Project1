<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $parents = ExpenseCategory::with('children')->whereNull('parent_id')->orderBy('name')->get();

        return view('admin.backend.expenses.expense_categories', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:expense_categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        ExpenseCategory::create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Expense category added.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        ExpenseCategory::findOrFail($id)->update([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Expense category updated.');
    }

    public function destroy($id)
    {
        // Removing a parent cascades to its sub-categories (FK cascadeOnDelete).
        ExpenseCategory::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Expense category deleted.');
    }
}
