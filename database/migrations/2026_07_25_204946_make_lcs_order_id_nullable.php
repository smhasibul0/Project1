<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LCs are maintained independently of orders: the order link becomes optional
     * and deleting an order releases its LCs instead of cascading into them.
     */
    public function up(): void
    {
        Schema::table('lcs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('lcs', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lcs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('lcs', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
