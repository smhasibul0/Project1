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
        Schema::create('lc_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lc_id')->constrained('lcs')->cascadeOnDelete();
            $table->foreignId('cost_category_id')->nullable()->constrained('cost_categories')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('cost_date')->nullable();
            $table->string('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lc_costs');
    }
};
