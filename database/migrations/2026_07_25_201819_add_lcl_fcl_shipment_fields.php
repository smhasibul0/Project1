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
        Schema::table('containers', function (Blueprint $table) {
            // FCL = whole container booked; LCL = consolidator space billed per CBM.
            $table->string('shipment_type')->default('fcl')->after('container_code');
            $table->string('container_size')->nullable()->after('container_number'); // 20GP / 40GP / 40HQ / 45HQ (FCL)
            $table->decimal('lcl_rate', 15, 2)->nullable()->after('container_size'); // freight rate per CBM (LCL)
        });

        Schema::table('quotations', function (Blueprint $table) {
            // Predicted freight for the cost projection: LCL (rate x total CBM) or FCL (flat container price).
            $table->string('freight_type')->nullable()->after('total_duty'); // lcl / fcl
            $table->decimal('freight_rate', 15, 2)->nullable()->after('freight_type'); // per CBM (LCL)
            $table->string('freight_container_size')->nullable()->after('freight_rate'); // FCL
            $table->decimal('freight_amount', 15, 2)->default(0)->after('freight_container_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['freight_type', 'freight_rate', 'freight_container_size', 'freight_amount']);
        });

        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn(['shipment_type', 'container_size', 'lcl_rate']);
        });
    }
};
