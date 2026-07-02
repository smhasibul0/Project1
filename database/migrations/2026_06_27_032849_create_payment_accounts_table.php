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
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('account_type_id')->constrained('account_types')->restrictOnDelete();
            $table->string('account_number')->nullable();
            $table->text('account_details')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(1);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
