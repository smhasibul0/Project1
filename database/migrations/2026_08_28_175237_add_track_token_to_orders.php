<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public tracking used to take an order number, and order numbers run in
 * sequence — OR0001, OR0002 — so anyone could walk the whole book and read
 * other customers' shipments. Each order now carries a random token instead,
 * and the tracking link is built from that.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('track_token', 40)->nullable()->unique()->after('order_no');
        });

        foreach (DB::table('orders')->pluck('id') as $id) {
            DB::table('orders')->where('id', $id)->update(['track_token' => Str::random(32)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['track_token']);
            $table->dropColumn('track_token');
        });
    }
};
