<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files each cost type under the shared expense category tree, so an
     * office cost reads as Category › Sub-category › Type (Utilities ›
     * Electricity › Office Electricity Bill) the same way warehouse expenses
     * already do. Nullable: a type can sit uncategorised.
     */
    public function up(): void
    {
        Schema::table('office_cost_types', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('name')
                ->constrained('expense_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('office_cost_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
        });
    }
};
