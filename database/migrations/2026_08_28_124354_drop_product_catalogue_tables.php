<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business forwards and clears other people's goods — it never owns a
 * catalogue — so products, their stock, brands, categories and units are retired.
 * An item is now identified by its HS code and description (see hs_codes).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('units');
    }

    /**
     * Reverse the migrations.
     *
     * The catalogue tables are rebuilt by their own migrations; the columns come
     * back without their foreign keys because the rows they pointed at are gone.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('id');
            $table->unsignedBigInteger('category_id')->nullable()->after('description');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('item_description');
            $table->unsignedBigInteger('unit_id')->nullable()->after('quantity');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('item_description');
            $table->unsignedBigInteger('unit_id')->nullable()->after('category_id');
        });
    }
};
