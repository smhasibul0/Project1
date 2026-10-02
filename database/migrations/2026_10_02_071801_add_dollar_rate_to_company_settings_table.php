<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one USD → BDT rate that turns a valuation report's dollars per kilogram
     * into a quotation item's declared value in taka, and the day it was set.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('usd_rate', 12, 4)->nullable();
            $table->date('usd_rate_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['usd_rate', 'usd_rate_date']);
        });
    }
};
