<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments made against a warehouse expense — an expense keeps its accrued
     * total in `warehouse_expenses.amount` and can be settled by multiple
     * partial payments over time.
     */
    public function up(): void
    {
        Schema::create('warehouse_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_expense_id')->constrained('warehouse_expenses')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('paid_on');
            $table->string('method')->nullable(); // Cash / Bank Transfer / Cheque / Mobile Banking / Other
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Backfill: expenses recorded before this module were paid in full at
        // creation time — turn each into a single payment row and repoint its
        // ledger transaction so payment-level reversal keeps working.
        $legacy = DB::table('warehouse_expenses')->whereNotNull('payment_account_id')->get();
        foreach ($legacy as $expense) {
            $paymentId = DB::table('warehouse_expense_payments')->insertGetId([
                'warehouse_expense_id' => $expense->id,
                'amount' => $expense->amount,
                'paid_on' => $expense->expense_date,
                'payment_account_id' => $expense->payment_account_id,
                'added_by' => $expense->added_by,
                'created_at' => $expense->created_at,
                'updated_at' => $expense->updated_at,
            ]);

            DB::table('transactions')
                ->where('transactionable_type', 'App\\Models\\WarehouseExpense')
                ->where('transactionable_id', $expense->id)
                ->update([
                    'transactionable_type' => 'App\\Models\\WarehouseExpensePayment',
                    'transactionable_id' => $paymentId,
                ]);
        }

        // The account link now lives on the payment rows.
        Schema::table('warehouse_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_expenses', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('expense_date')->constrained('payment_accounts')->nullOnDelete();
        });

        // Best effort: restore the first payment's account onto the expense and
        // repoint ledger transactions back before dropping the payments table.
        foreach (DB::table('warehouse_expense_payments')->orderBy('id')->get() as $payment) {
            DB::table('warehouse_expenses')
                ->where('id', $payment->warehouse_expense_id)
                ->whereNull('payment_account_id')
                ->update(['payment_account_id' => $payment->payment_account_id]);

            DB::table('transactions')
                ->where('transactionable_type', 'App\\Models\\WarehouseExpensePayment')
                ->where('transactionable_id', $payment->id)
                ->update([
                    'transactionable_type' => 'App\\Models\\WarehouseExpense',
                    'transactionable_id' => $payment->warehouse_expense_id,
                ]);
        }

        Schema::dropIfExists('warehouse_expense_payments');
    }
};
