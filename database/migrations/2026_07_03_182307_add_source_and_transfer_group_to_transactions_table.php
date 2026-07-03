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
        Schema::table('transactions', function (Blueprint $table) {
            // How the transaction was created: deposit / fund_transfer / null (system entries
            // such as purchase & sale payments that must not be edited from the account book).
            $table->string('source')->nullable()->after('type');
            // Shared id linking the two legs (debit + credit) of a single fund transfer.
            $table->string('transfer_group')->nullable()->after('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['source', 'transfer_group']);
        });
    }
};
