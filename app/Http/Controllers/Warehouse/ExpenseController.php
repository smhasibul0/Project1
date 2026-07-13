<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\WarehouseExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index()
    {
        $warehouseId = Auth::user()->warehouse_id;

        $expenses = WarehouseExpense::with(['category.parent', 'paymentAccount'])
            ->where('warehouse_id', $warehouseId)
            ->latest('expense_date')->latest('id')->get();

        return view('warehouse.expenses.index', [
            'warehouse' => Auth::user()->warehouse,
            'expenses' => $expenses,
            'total' => round($expenses->sum(fn ($e) => (float) $e->amount), 2),
            'categories' => ExpenseCategory::with('children')->whereNull('parent_id')->where('is_active', true)->orderBy('name')->get(),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Record a warehouse expense. When paid from an account, it debits that account's ledger.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        $warehouse = Auth::user()->warehouse;

        DB::transaction(function () use ($data, $request, $warehouse) {
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachment = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $dir = public_path('upload/expenses');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file->move($dir, $attachment);
            }

            $expense = WarehouseExpense::create([
                'warehouse_id' => $warehouse->id,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'note' => $data['note'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            if (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->decrement('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'debit',
                    'source' => 'warehouse_expense',
                    'amount' => $data['amount'],
                    'credit' => 0,
                    'debit' => $data['amount'],
                    'running_balance' => $account->fresh()->balance,
                    'description' => ($expense->category->name ?? 'Expense').' — '.$warehouse->name,
                    'reference' => $warehouse->name,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => WarehouseExpense::class,
                    'transactionable_id' => $expense->id,
                    'added_by' => Auth::id(),
                    'created_at' => $expense->expense_date,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Expense of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete an expense, reversing its ledger entry (crediting the account back) when paid
     * from a payment account.
     */
    public function destroy($id)
    {
        $expense = WarehouseExpense::where('warehouse_id', Auth::user()->warehouse_id)->findOrFail($id);

        DB::transaction(function () use ($expense) {
            if ($expense->payment_account_id) {
                $account = PaymentAccount::find($expense->payment_account_id);
                if ($account) {
                    $account->increment('balance', (float) $expense->amount);
                }
                Transaction::where('transactionable_type', WarehouseExpense::class)
                    ->where('transactionable_id', $expense->id)
                    ->delete();
            }

            $expense->delete();
        });

        return redirect()->back()->with('success', 'Expense deleted.');
    }
}
