<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments made against an office expense — an expense keeps its accrued
     * total in `office_expenses.amount` and can be settled by multiple partial
     * payments, each drawn from its own payment account.
     */
    public function up(): void
    {
        Schema::create('office_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_expense_id')->constrained('office_expenses')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('paid_on');
            $table->string('method')->nullable(); // Cash / Bank Transfer / Cheque / Mobile Banking / Other
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_expense_payments');
    }
};
