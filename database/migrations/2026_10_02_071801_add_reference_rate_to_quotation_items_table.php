<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an item's declared value was worked out from: the reference price per
     * kilogram, the date of the rate it came from and the dollar rate used — kept on
     * the item so the quotation still shows its basis after newer rates are uploaded.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('reference_unit_price', 14, 4)->nullable();
            $table->date('reference_rate_date')->nullable();
            $table->decimal('reference_usd_rate', 12, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['reference_unit_price', 'reference_rate_date', 'reference_usd_rate']);
        });
    }
};
