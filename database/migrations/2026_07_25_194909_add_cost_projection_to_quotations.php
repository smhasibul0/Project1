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
        Schema::table('quotations', function (Blueprint $table) {
            // Mandatory packing list attachment (nullable for legacy rows).
            $table->string('packing_list_path')->nullable()->after('remarks');

            // Rolled-up cost projection: duties + predicted LC/custom expenses vs. asking total.
            $table->decimal('total_duty', 15, 2)->default(0)->after('profit_margin');
            $table->decimal('projected_cost_total', 15, 2)->default(0)->after('total_duty');
            $table->decimal('projected_profit', 15, 2)->default(0)->after('projected_cost_total');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            // Free-text item name for lines imported from a supplier packing list.
            $table->string('description')->nullable()->after('product_id');

            // Bangladesh Customs duty projection (rates in %, cascade computed per item).
            // Assessable value defaults to supplier cost x qty + 1% landing charge but can be overridden.
            $table->decimal('assessable_value', 15, 2)->nullable()->after('supplier_quotation_date');
            $table->decimal('cd_rate', 8, 2)->default(0)->after('assessable_value');
            $table->decimal('rd_rate', 8, 2)->default(0)->after('cd_rate');
            $table->decimal('sd_rate', 8, 2)->default(0)->after('rd_rate');
            $table->decimal('vat_rate', 8, 2)->default(0)->after('sd_rate');
            $table->decimal('ait_rate', 8, 2)->default(0)->after('vat_rate');
            $table->decimal('at_rate', 8, 2)->default(0)->after('ait_rate');
            $table->decimal('duty_amount', 15, 2)->default(0)->after('at_rate');
        });

        Schema::create('quotation_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('expense_group')->default('custom'); // lc / custom
            $table->foreignId('cost_category_id')->nullable()->constrained('cost_categories')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_expenses');

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'assessable_value', 'cd_rate', 'rd_rate', 'sd_rate',
                'vat_rate', 'ait_rate', 'at_rate', 'duty_amount',
            ]);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['packing_list_path', 'total_duty', 'projected_cost_total', 'projected_profit']);
        });
    }
};
