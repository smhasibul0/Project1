<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day a loan was settled or written off, so the balance sheet for an earlier
     * date still counts it. Loans closed before now take the day they were last changed.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('closed_on')->nullable()->after('status');
        });

        foreach (DB::table('loans')->where('status', '!=', 'active')->get(['id', 'updated_at']) as $loan) {
            DB::table('loans')->where('id', $loan->id)->update([
                'closed_on' => Carbon::parse($loan->updated_at)->toDateString(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('closed_on');
        });
    }
};
