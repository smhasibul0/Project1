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
        Schema::create('hs_codes', function (Blueprint $table) {
            $table->id();

            // Formatted tariff code as printed (0101.21.00) plus a digits-only
            // copy (01012100) so a search matches either spelling. The digits are
            // the identity — the same line re-imported in another format updates it.
            $table->string('code', 30)->index();
            $table->string('code_digits', 30)->unique();
            $table->string('heading', 30)->nullable();

            $table->text('description');
            $table->string('statistical_unit', 30)->nullable();

            // Bangladesh Customs rates in % (the tariff book only carries CD;
            // the rest arrive from a later rates sheet or are keyed in by hand).
            $table->decimal('cd_rate', 8, 2)->default(0);
            $table->decimal('sd_rate', 8, 2)->default(0);
            $table->decimal('vat_rate', 8, 2)->default(0);
            $table->decimal('ait_rate', 8, 2)->default(0);
            $table->decimal('rd_rate', 8, 2)->default(0);
            $table->decimal('at_rate', 8, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hs_codes');
    }
};
