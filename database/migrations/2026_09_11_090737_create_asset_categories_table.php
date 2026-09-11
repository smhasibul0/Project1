<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groups for the asset register — Furniture, Vehicles, IT Equipment and so
     * on. Each category carries the depreciation defaults new assets of that
     * kind start from, so the register stays consistent.
     */
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('default_method')->default('straight_line'); // straight_line / reducing_balance / none
            $table->unsignedSmallInteger('default_useful_life_years')->nullable();
            $table->decimal('default_rate', 5, 2)->nullable(); // reducing balance, % a year
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
