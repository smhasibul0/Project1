<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ledger entry or an LC charge paid in dollars keeps its dollar amount and the
     * rate it was converted at beside the taka figure — so an account can show the
     * dollars sent through it.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('usd_amount', 15, 2)->nullable();
            $table->decimal('usd_rate', 12, 4)->nullable();
        });

        Schema::table('lc_costs', function (Blueprint $table) {
            $table->decimal('usd_amount', 15, 2)->nullable();
            $table->decimal('usd_rate', 12, 4)->nullable();
        });

        // A dollar LC's bank charge used to be counted as that many taka. Work out
        // every affected order again so its LC cost and profit come out right.
        $orderIds = DB::table('lcs')
            ->where('currency', 'USD')
            ->where('bank_charges', '>', 0)
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id');

        foreach (Order::whereIn('id', $orderIds)->get() as $order) {
            $order->recomputeFinancials();
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['usd_amount', 'usd_rate']);
        });

        Schema::table('lc_costs', function (Blueprint $table) {
            $table->dropColumn(['usd_amount', 'usd_rate']);
        });
    }
};
