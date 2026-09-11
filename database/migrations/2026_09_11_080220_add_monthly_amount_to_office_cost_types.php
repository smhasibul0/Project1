<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The standard amount a fixed cost type bills each month, used to generate
     * that month's expense rows in one click. Variable types leave it null.
     */
    public function up(): void
    {
        Schema::table('office_cost_types', function (Blueprint $table) {
            $table->decimal('monthly_amount', 15, 2)->nullable()->after('nature');
        });
    }

    public function down(): void
    {
        Schema::table('office_cost_types', function (Blueprint $table) {
            $table->dropColumn('monthly_amount');
        });
    }
};
