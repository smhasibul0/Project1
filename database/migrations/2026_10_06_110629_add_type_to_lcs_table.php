<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The register holds three ways of paying a supplier: a letter of credit (LC),
     * cash against documents (CAD) and a telegraphic transfer (TT). Every record
     * so far is an LC.
     */
    public function up(): void
    {
        Schema::table('lcs', function (Blueprint $table) {
            $table->string('type', 5)->default('lc')->after('lc_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('lcs', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
