<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->cascadeOnDelete();

            $table->string('type')->default('credit'); // credit / debit
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('running_balance', 15, 2)->default(0);

            $table->string('description')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_details')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            // Polymorphic link to the source document (Purchase / Sale / Expense / FundTransfer …)
            $table->nullableMorphs('transactionable');

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
