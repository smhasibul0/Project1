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
        Schema::table('contacts', function (Blueprint $table) {
            $table->text('bank_details')->nullable()->after('tax_number');
            $table->text('warehouse_address')->nullable()->after('country');
            $table->string('shipping_mark')->nullable()->after('warehouse_address');
            $table->text('more_information')->nullable()->after('shipping_mark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['bank_details', 'warehouse_address', 'shipping_mark', 'more_information']);
        });
    }
};
