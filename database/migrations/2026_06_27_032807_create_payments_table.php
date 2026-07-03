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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // NOTE: purchases, payment_methods & payment_accounts are constrained in later
            // phases. The payment_accounts table is created by a *later* migration than this
            // one, so the FK can't be declared here yet. Columns are kept so payment records
            // can reference them once the constraints are added.
            $table->foreignId('purchase_id')->nullable();
            $table->foreignId('payment_method_id')->nullable();
            $table->foreignId('payment_account_id')->nullable();

            $table->decimal('amount', 12, 2)->default(0);
            $table->dateTime('paid_on');
            $table->string('type')->default('purchase_payment'); // purchase_payment / sale_payment / expense etc.
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
