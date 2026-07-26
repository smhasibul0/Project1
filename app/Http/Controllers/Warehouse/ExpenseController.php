<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\WarehouseExpense;
use App\Models\WarehouseExpensePayment;
use App\Support\CurrentWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    /** @var list<string> */
    public const PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'Cheque', 'Mobile Banking', 'Other'];

    public function index()
    {
        $expenses = WarehouseExpense::with(['category.parent', 'payments.paymentAccount'])
            ->where('warehouse_id', CurrentWarehouse::id())
            ->latest('expense_date')->latest('id')->get();

        return view('warehouse.expenses.index', [
            'warehouse' => CurrentWarehouse::get(),
            'expenses' => $expenses,
            'total' => round($expenses->sum(fn ($e) => (float) $e->amount), 2),
            'totalDue' => round($expenses->sum(fn ($e) => $e->dueTotal()), 2),
            'categories' => ExpenseCategory::with('children')->whereNull('parent_id')->where('is_active', true)->orderBy('name')->get(),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Record a warehouse expense, optionally with an initial payment. The
     * expense keeps its full accrued amount; payments can settle it over time.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
            'payment_amount' => 'nullable|required_with:payment_account_id|numeric|min:0.01|lte:amount',
            'paid_on' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'payment_note' => 'nullable|string|max:255',
        ]);

        $warehouse = CurrentWarehouse::get();

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
                'note' => $data['note'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            if (! empty($data['payment_amount'])) {
                $this->recordPayment($expense, [
                    'amount' => $data['payment_amount'],
                    'paid_on' => $data['paid_on'] ?? $data['expense_date'],
                    'method' => $data['payment_method'] ?? null,
                    'payment_account_id' => $data['payment_account_id'] ?? null,
                    'note' => $data['payment_note'] ?? null,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Expense of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Edit an expense's own details. Payments are managed separately, so the
     * total can never drop below what has already been paid.
     */
    public function update(Request $request, $id)
    {
        $expense = WarehouseExpense::with('payments')
            ->where('warehouse_id', CurrentWarehouse::id())
            ->findOrFail($id);

        $data = $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        if ((float) $data['amount'] + 0.005 < $expense->paidTotal()) {
            throw ValidationException::withMessages([
                'amount' => 'Total cannot be less than the '.number_format($expense->paidTotal(), 2).' already paid. Delete a payment first.',
            ]);
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment'] = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $dir = public_path('upload/expenses');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file->move($dir, $data['attachment']);
        }

        $expense->update($data);

        return redirect()->back()->with('success', 'Expense updated.');
    }

    /**
     * Add a payment against an existing expense (partial payments allowed,
     * never more than the outstanding due).
     */
    public function storePayment(Request $request, $id)
    {
        $expense = WarehouseExpense::with('payments')
            ->where('warehouse_id', CurrentWarehouse::id())
            ->findOrFail($id);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date',
            'method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:255',
        ]);

        if ((float) $data['amount'] > $expense->dueTotal() + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the outstanding due of '.number_format($expense->dueTotal(), 2).'.',
            ]);
        }

        DB::transaction(function () use ($expense, $data) {
            $this->recordPayment($expense, $data);
        });

        return redirect()->back()->with('success', 'Payment of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete a single payment, crediting its account back.
     */
    public function destroyPayment($id, $paymentId)
    {
        $expense = WarehouseExpense::where('warehouse_id', CurrentWarehouse::id())->findOrFail($id);
        $payment = $expense->payments()->findOrFail($paymentId);

        DB::transaction(function () use ($payment) {
            $this->reversePayment($payment);
            $payment->delete();
        });

        return redirect()->back()->with('success', 'Payment deleted.');
    }

    /**
     * Delete an expense, reversing every payment's ledger entry (crediting the
     * accounts back).
     */
    public function destroy($id)
    {
        $expense = WarehouseExpense::with('payments')
            ->where('warehouse_id', CurrentWarehouse::id())
            ->findOrFail($id);

        DB::transaction(function () use ($expense) {
            foreach ($expense->payments as $payment) {
                $this->reversePayment($payment);
            }

            $expense->delete();
        });

        return redirect()->back()->with('success', 'Expense deleted.');
    }

    /**
     * Create a payment row and, when paid from an account, debit that
     * account's ledger.
     *
     * @param  array<string, mixed>  $data
     */
    private function recordPayment(WarehouseExpense $expense, array $data): void
    {
        $payment = $expense->payments()->create([
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'method' => $data['method'] ?? null,
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'note' => $data['note'] ?? null,
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
                'description' => ($expense->category->name ?? 'Expense').' — '.$expense->warehouse->name,
                'payment_method' => $data['method'] ?? null,
                'reference' => $expense->warehouse->name,
                'note' => $data['note'] ?? null,
                'transactionable_type' => WarehouseExpensePayment::class,
                'transactionable_id' => $payment->id,
                'added_by' => Auth::id(),
                'created_at' => $data['paid_on'],
            ]);
        }
    }

    /**
     * Credit a payment's account back and remove its ledger entry.
     */
    private function reversePayment(WarehouseExpensePayment $payment): void
    {
        if (! $payment->payment_account_id) {
            return;
        }

        $account = PaymentAccount::find($payment->payment_account_id);
        if ($account) {
            $account->increment('balance', (float) $payment->amount);
        }

        Transaction::where('transactionable_type', WarehouseExpensePayment::class)
            ->where('transactionable_id', $payment->id)
            ->delete();
    }
}
