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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no')->unique();
            $table->date('query_received_date')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->boolean('submitted_to_customer')->default(false);
            $table->string('status')->default('draft'); // draft / submitted / accepted / rejected / converted

            // Aggregates rolled up from the items.
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            $table->decimal('profit_margin', 8, 2)->default(0);

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
        Schema::dropIfExists('quotations');
    }
};
