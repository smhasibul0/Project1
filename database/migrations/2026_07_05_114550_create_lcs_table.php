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
        Schema::create('lcs', function (Blueprint $table) {
            $table->id();
            $table->string('lc_code')->unique(); // internal auto reference LC####
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->date('pi_date')->nullable();
            $table->string('pi_no')->nullable();
            $table->string('pi_document')->nullable(); // attached purchase invoice file

            $table->string('lc_number')->nullable(); // the opening bank's LC number
            $table->string('opening_bank')->nullable();
            $table->string('container_no')->nullable();
            $table->text('commodity')->nullable();

            $table->decimal('invoice_amount', 15, 2)->default(0); // Inv. Amount (Sent)
            $table->string('currency', 10)->default('USD');
            $table->decimal('net_amount_received', 15, 2)->nullable(); // counterpart received
            $table->decimal('bank_charges', 15, 2)->default(0); // auto = invoice_amount - net_amount_received

            $table->decimal('usd_sell_rate', 15, 4)->nullable(); // China USD sell rate
            $table->date('usd_sell_date')->nullable();

            $table->string('lc_status')->default('draft'); // draft/opened/released/settled/cancelled
            $table->date('released_date')->nullable();

            $table->text('remarks')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lcs');
    }
};
