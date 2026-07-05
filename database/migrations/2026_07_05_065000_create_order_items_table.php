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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('item_description')->nullable(); // nature of goods
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('hs_code')->nullable();

            $table->decimal('quantity', 15, 2)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('package_quantity', 15, 2)->default(0);
            $table->decimal('net_weight', 15, 3)->nullable();      // PL cargo net weight (kg)
            $table->decimal('cbm', 15, 4)->nullable();             // PL cargo volume
            $table->decimal('actual_weight', 15, 3)->nullable();   // actual weight from BD warehouse

            $table->decimal('supplier_asking_price', 15, 2)->default(0); // cost, for profit
            $table->decimal('our_asking_price', 15, 2)->default(0);      // rate to client
            $table->decimal('line_total', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
