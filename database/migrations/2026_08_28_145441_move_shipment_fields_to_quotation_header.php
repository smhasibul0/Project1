<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One shipment travels one way from one place, so the transport mode and the country
 * of loading belong to the quotation, not to each line on it. Orders already hold
 * both on their header; quotations now match.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('transportation_mode_id')->nullable()->after('customer_id')
                ->constrained('transportation_modes')->nullOnDelete();
            $table->string('country_of_loading')->nullable()->after('transportation_mode_id');
        });

        // Lift what the lines already carried up to the quotation they belong to.
        $lines = DB::table('quotation_items')
            ->select('quotation_id', 'transportation_mode_id', 'country_of_loading')
            ->where(function ($query) {
                $query->whereNotNull('transportation_mode_id')->orWhereNotNull('country_of_loading');
            })
            ->get()
            ->keyBy('quotation_id');

        foreach ($lines as $quotationId => $line) {
            DB::table('quotations')->where('id', $quotationId)->update([
                'transportation_mode_id' => $line->transportation_mode_id,
                'country_of_loading' => $line->country_of_loading,
            ]);
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transportation_mode_id');
            $table->dropColumn('country_of_loading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('transportation_mode_id')->nullable()->after('hs_code')
                ->constrained('transportation_modes')->nullOnDelete();
            $table->string('country_of_loading')->nullable()->after('transportation_mode_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transportation_mode_id');
            $table->dropColumn('country_of_loading');
        });
    }
};
