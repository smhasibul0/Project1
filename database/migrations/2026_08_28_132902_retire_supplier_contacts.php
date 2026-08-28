<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The company buys nothing, so it keeps no supplier directory — every contact is
 * a customer. Firms that were both stay on as customers; supplier-only rows go,
 * along with the fields that only ever described a supplier.
 *
 * An LC's proforma invoice is still issued by someone abroad, so the link to a
 * supplier record becomes a plain shipper name typed on the LC.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lcs', function (Blueprint $table) {
            $table->string('shipper')->nullable()->after('order_id');
        });

        // Carry the supplier's name over to the LCs that referenced it.
        foreach (DB::table('lcs')->whereNotNull('supplier_id')->get(['id', 'supplier_id']) as $lc) {
            $supplier = DB::table('contacts')->where('id', $lc->supplier_id)->first(['name', 'business_name']);

            if ($supplier !== null) {
                DB::table('lcs')->where('id', $lc->id)
                    ->update(['shipper' => $supplier->business_name ?: $supplier->name]);
            }
        }

        Schema::table('lcs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        DB::table('contacts')->where('type', 'both')->update(['type' => 'customer']);
        DB::table('contacts')->where('type', 'supplier')->delete();

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'bank_details', 'warehouse_address', 'shipping_mark']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The supplier rows themselves are gone; the columns come back empty.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('type')->default('customer')->after('id');
            $table->text('bank_details')->nullable()->after('tax_number');
            $table->text('warehouse_address')->nullable()->after('country');
            $table->string('shipping_mark')->nullable()->after('warehouse_address');
            $table->index('type');
        });

        Schema::table('lcs', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('order_id')->constrained('contacts')->nullOnDelete();
            $table->dropColumn('shipper');
        });
    }
};
