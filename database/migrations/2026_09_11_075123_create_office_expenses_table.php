<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Office running costs. An expense holds its full accrued amount and is
     * settled by one or more payments, mirroring warehouse expenses.
     */
    public function up(): void
    {
        Schema::create('office_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_cost_type_id')->nullable()->constrained('office_cost_types')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('expense_date')->index();
            $table->text('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_expenses');
    }
};
