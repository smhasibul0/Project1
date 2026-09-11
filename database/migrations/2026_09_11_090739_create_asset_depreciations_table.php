<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One posted depreciation charge per asset per month. Keeping each month
     * as its own row means the register can be re-run safely, a wrong month
     * can be reversed on its own, and the P&L can charge depreciation to the
     * period it belongs to.
     */
    public function up(): void
    {
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('period'); // always the first of the month charged
            $table->decimal('amount', 15, 2);
            $table->decimal('book_value_after', 15, 2);
            $table->string('note')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A month can only ever be charged once per asset.
            $table->unique(['asset_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
    }
};
