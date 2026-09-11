<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company's fixed asset register. Each row holds what the asset cost,
     * what it is expected to be worth at the end of its life, and the basis
     * its depreciation is charged on; the running charge itself lives in
     * asset_depreciations so every month posted is auditable.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);

            // straight_line / reducing_balance / none (land and the like).
            $table->string('depreciation_method')->default('straight_line');
            $table->unsignedSmallInteger('useful_life_years')->nullable();
            $table->decimal('depreciation_rate', 5, 2)->nullable(); // reducing balance, % a year

            $table->string('status')->default('in_use'); // in_use / disposed / written_off
            $table->date('disposed_on')->nullable();
            $table->decimal('disposal_amount', 15, 2)->nullable();

            $table->string('serial_no')->nullable();
            $table->string('location')->nullable();
            $table->string('supplier')->nullable();
            $table->text('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'purchase_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
