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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->date('order_date')->nullable();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Shipment / logistics
            $table->string('shipment_no')->nullable();
            $table->string('shipping_mark')->nullable();
            $table->foreignId('transportation_mode_id')->nullable()->constrained('transportation_modes')->nullOnDelete();
            $table->string('country_of_loading')->nullable();
            $table->foreignId('packing_type_id')->nullable()->constrained('packing_types')->nullOnDelete();
            $table->date('goods_handover_date')->nullable();
            $table->date('tentative_receive_date')->nullable();
            $table->date('port_arrival_date')->nullable();
            $table->date('bd_warehouse_date')->nullable();
            $table->integer('total_delivery_days')->nullable();

            // Tracking + delivery
            $table->string('goods_status')->default('pending'); // pipeline stage
            $table->string('delivery_status')->default('pending'); // pending / delivered
            $table->date('delivered_date')->nullable();

            // Financials
            $table->decimal('subtotal', 15, 2)->default(0);          // Σ line totals (receivable)
            $table->string('discount_type')->default('fixed');       // fixed / percentage
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);      // after discount
            $table->string('payment_status')->default('due');        // due / partial / paid
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->decimal('due_amount', 15, 2)->default(0);
            $table->date('amount_received_date')->nullable();
            $table->decimal('total_expense', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);

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
        Schema::dropIfExists('orders');
    }
};
