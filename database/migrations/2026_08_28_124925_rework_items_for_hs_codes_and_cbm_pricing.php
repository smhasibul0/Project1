<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations and orders are priced as freight, not as goods.
 *
 * An item line is a tariff line: the HS code brings its description and the six
 * duty rates, and the customer's declared invoice value is what duty is assessed
 * on. Money is charged per CBM at one rate for the whole shipment, so the per-item
 * buy/sell prices go and a cost rate + sell rate arrive on the header.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('hs_code_id')->nullable()->after('id')->constrained('hs_codes')->nullOnDelete();

            // What the customer declares the goods on this line are worth.
            $table->renameColumn('supplier_asking_price', 'declared_value');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['supplier_quotation_date', 'our_asking_price', 'unit_profit', 'profit_margin']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            // freight_rate stays as the cost per CBM; this is what we charge for it.
            $table->decimal('sell_rate_per_cbm', 15, 2)->default(0)->after('freight_amount');
            $table->decimal('total_cbm', 15, 4)->default(0)->after('sell_rate_per_cbm');
            $table->decimal('customer_charge', 15, 2)->default(0)->after('total_cbm');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('hs_code_id')->nullable()->after('id')->constrained('hs_codes')->nullOnDelete();
            $table->renameColumn('supplier_asking_price', 'declared_value');

            // The duty projection carried over from the quotation.
            $table->decimal('assessable_value', 15, 2)->nullable()->after('declared_value');
            $table->decimal('cd_rate', 8, 2)->default(0)->after('assessable_value');
            $table->decimal('rd_rate', 8, 2)->default(0)->after('cd_rate');
            $table->decimal('sd_rate', 8, 2)->default(0)->after('rd_rate');
            $table->decimal('vat_rate', 8, 2)->default(0)->after('sd_rate');
            $table->decimal('ait_rate', 8, 2)->default(0)->after('vat_rate');
            $table->decimal('at_rate', 8, 2)->default(0)->after('ait_rate');
            $table->decimal('duty_amount', 15, 2)->default(0)->after('at_rate');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('our_asking_price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cost_rate_per_cbm', 15, 2)->default(0)->after('total_delivery_days');
            $table->decimal('sell_rate_per_cbm', 15, 2)->default(0)->after('cost_rate_per_cbm');
            $table->decimal('total_cbm', 15, 4)->default(0)->after('sell_rate_per_cbm');
            $table->decimal('freight_cost', 15, 2)->default(0)->after('total_expense');
            $table->decimal('duty_total', 15, 2)->default(0)->after('freight_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cost_rate_per_cbm', 'sell_rate_per_cbm', 'total_cbm', 'freight_cost', 'duty_total']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hs_code_id');
            $table->renameColumn('declared_value', 'supplier_asking_price');
            $table->dropColumn([
                'assessable_value', 'cd_rate', 'rd_rate', 'sd_rate',
                'vat_rate', 'ait_rate', 'at_rate', 'duty_amount',
            ]);
            $table->decimal('our_asking_price', 15, 2)->default(0);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['sell_rate_per_cbm', 'total_cbm', 'customer_charge']);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hs_code_id');
            $table->renameColumn('declared_value', 'supplier_asking_price');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->date('supplier_quotation_date')->nullable();
            $table->decimal('our_asking_price', 15, 2)->default(0);
            $table->decimal('unit_profit', 15, 2)->default(0);
            $table->decimal('profit_margin', 8, 2)->default(0);
        });
    }
};
