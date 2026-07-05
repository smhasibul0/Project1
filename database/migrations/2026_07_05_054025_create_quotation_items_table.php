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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('hs_code')->nullable();
            $table->foreignId('transportation_mode_id')->nullable()->constrained('transportation_modes')->nullOnDelete();
            $table->string('country_of_loading')->nullable();
            $table->foreignId('packing_type_id')->nullable()->constrained('packing_types')->nullOnDelete();

            $table->decimal('package_quantity', 15, 2)->default(0);
            $table->decimal('net_weight', 15, 3)->nullable();
            $table->decimal('gross_weight', 15, 3)->nullable();
            $table->decimal('length', 12, 2)->nullable();
            $table->decimal('width', 12, 2)->nullable();
            $table->decimal('height', 12, 2)->nullable();
            $table->decimal('cbm', 15, 4)->nullable();

            // Supplier (cost) side
            $table->decimal('supplier_asking_price', 15, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->date('supplier_quotation_date')->nullable();

            // Customer (sell) side + computed profit
            $table->decimal('our_asking_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('unit_profit', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            $table->decimal('profit_margin', 8, 2)->default(0);

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
