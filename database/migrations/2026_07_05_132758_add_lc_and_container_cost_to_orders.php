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
        Schema::table('orders', function (Blueprint $table) {
            // LC charges + allocated container costs feed profit alongside order costs.
            $table->decimal('lc_cost', 15, 2)->default(0)->after('total_expense');
            $table->decimal('container_cost', 15, 2)->default(0)->after('lc_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['lc_cost', 'container_cost']);
        });
    }
};
