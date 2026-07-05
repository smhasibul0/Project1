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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('container_code')->unique(); // internal auto reference CNT####
            $table->string('shipment_no')->nullable();  // e.g. 25/2026 (CTG)
            $table->string('container_number')->nullable();
            $table->string('lc_number')->nullable();     // informational
            $table->string('job_no')->nullable();
            $table->string('transport_mode')->nullable();
            $table->string('shipping_line')->nullable();
            $table->string('booking_ref')->nullable();   // HBL or MBL No.
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->date('etd')->nullable();             // estimated departure
            $table->date('eta')->nullable();             // estimated arrival
            $table->string('status')->default('booked'); // booked/in_transit/at_port/released/delivered
            $table->string('allocation_basis')->default('cbm'); // cbm/weight/ctn/equal
            $table->text('staffing_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
