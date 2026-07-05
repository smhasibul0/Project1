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
        Schema::create('container_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // The portion of this order riding in this container (an order may split across containers).
            $table->decimal('ctn', 15, 2)->default(0);
            $table->decimal('weight', 15, 2)->default(0);
            $table->decimal('cbm', 15, 4)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['container_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_order');
    }
};
