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
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
 
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
