<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move any existing flat order expenses into the structured order_costs table,
     * then drop the legacy order_expenses table.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_expenses')) {
            foreach (DB::table('order_expenses')->get() as $expense) {
                DB::table('order_costs')->insert([
                    'order_id' => $expense->order_id,
                    'cost_category_id' => null,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'created_at' => $expense->created_at,
                    'updated_at' => $expense->updated_at,
                ]);
            }

            Schema::dropIfExists('order_expenses');
        }
    }

    /**
     * Recreate the legacy table (data is not restored).
     */
    public function down(): void
    {
        if (! Schema::hasTable('order_expenses')) {
            Schema::create('order_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('title');
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }
};
