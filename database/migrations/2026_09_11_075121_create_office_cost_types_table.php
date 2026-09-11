<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Office running-cost types. Each type carries its own nature, so every
     * expense booked against it is fixed or variable without the person
     * recording it having to decide.
     */
    public function up(): void
    {
        Schema::create('office_cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nature')->default('variable'); // fixed (recurs monthly) / variable (one-off)
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_cost_types');
    }
};
