<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per QR scan: the carton label is scanned at each point of the journey
 * and whoever scans it says how many cartons of that order they are handling.
 * Counting the rows up per stage shows how much of a consignment has arrived,
 * so a consignment that turns up in two lorries is no trouble.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('stage'); // china_warehouse / container_loaded / at_port / bd_warehouse
            $table->decimal('cartons', 15, 2)->default(0);
            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_scans');
    }
};
