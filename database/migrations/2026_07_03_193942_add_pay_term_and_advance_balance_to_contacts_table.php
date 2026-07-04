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
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedInteger('pay_term_number')->nullable()->after('credit_limit');
            $table->string('pay_term_type')->nullable()->after('pay_term_number'); // days / months
            // Advance (credit) balance the business holds for the contact; maintained by
            // the payments module in a later phase.
            $table->decimal('advance_balance', 15, 2)->default(0)->after('pay_term_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['pay_term_number', 'pay_term_type', 'advance_balance']);
        });
    }
};
