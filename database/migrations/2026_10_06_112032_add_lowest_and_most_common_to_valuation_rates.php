<?php

use App\Support\ValuationReportParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Beside the highest assessed price (unit_price), a rate keeps the lowest and
     * the most common one, so a quotation can be priced from any of the three. A
     * quotation item keeps the three it was offered and which one it used.
     */
    public function up(): void
    {
        Schema::table('valuation_rates', function (Blueprint $table) {
            $table->decimal('lowest_unit_price', 14, 4)->nullable()->after('unit_price');
            $table->decimal('common_unit_price', 14, 4)->nullable()->after('lowest_unit_price');
            $table->unsignedInteger('common_bills')->nullable()->after('common_unit_price');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('reference_basis', 10)->nullable()->after('reference_unit_price');
            $table->json('reference_options')->nullable()->after('reference_basis');
        });

        // Rates uploaded so far kept every bill, so their figures can be worked out now.
        DB::table('valuation_rates')->orderBy('id')->each(function (object $rate) {
            $usdBills = collect(json_decode((string) $rate->bills, true) ?: [])
                ->where('currency', 'USD')
                ->values()
                ->all();

            if ($usdBills === []) {
                return;
            }

            $figures = ValuationReportParser::summarise($usdBills);

            DB::table('valuation_rates')->where('id', $rate->id)->update([
                'lowest_unit_price' => $figures['lowest_unit_price'],
                'common_unit_price' => $figures['common_unit_price'],
                'common_bills' => $figures['common_bills'],
            ]);
        });

        // A line priced before now was priced from the highest.
        DB::table('quotation_items')->whereNotNull('reference_unit_price')->update(['reference_basis' => 'highest']);
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['reference_basis', 'reference_options']);
        });

        Schema::table('valuation_rates', function (Blueprint $table) {
            $table->dropColumn(['lowest_unit_price', 'common_unit_price', 'common_bills']);
        });
    }
};
