<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money borrowed from a bank or an individual, and money lent out to one.
     * Both directions live here because they are the same shape — a principal,
     * an interest basis and a schedule of payments — only the way the cash
     * moves is reversed.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code')->unique();
            $table->string('direction'); // borrowed (we owe) / lent (we are owed)
            $table->string('counterparty');
            $table->string('counterparty_type')->default('bank'); // bank / individual / company

            $table->decimal('principal', 15, 2);

            // percentage (a yearly rate), fixed (a flat charge) or none.
            $table->string('interest_type')->default('none');
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('interest_amount', 15, 2)->nullable();

            $table->date('start_date');
            $table->date('due_date')->nullable();

            // The account the principal landed in, or was paid out of.
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();

            $table->string('status')->default('active'); // active / settled / written_off
            $table->text('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
