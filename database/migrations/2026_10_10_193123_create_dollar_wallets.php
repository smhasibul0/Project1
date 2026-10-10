<?php

use App\Models\LcPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every payment account holds dollars beside its taka. Paying an LC buys dollars
     * with taka at the bank's rate and keeps them in the account; they pay costs
     * abroad, move between accounts, or are sold back for taka. The account keeps
     * what its dollars cost in taka, so spending them is costed at that average and
     * only a sale makes an exchange gain or loss.
     */
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->decimal('usd_balance', 15, 2)->default(0)->after('balance');
            // What the dollars held cost in taka; ÷ usd_balance is their average rate.
            $table->decimal('usd_cost', 15, 2)->default(0)->after('usd_balance');
        });

        Schema::create('dollar_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->cascadeOnDelete();
            $table->date('entry_date')->index();
            $table->string('direction', 3); // in | out
            $table->string('source', 30)->index();
            $table->decimal('usd_amount', 15, 2);
            // In: what a dollar cost. Out: the average it is costed at. Sale: what it sold for.
            $table->decimal('rate', 12, 4);
            // The taka cost of the dollars moved.
            $table->decimal('bdt_amount', 15, 2);
            // A sale's taka over (or under) what the dollars cost.
            $table->decimal('gain_loss', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->string('transfer_group', 36)->nullable()->index();
            $table->nullableMorphs('transactionable');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->keepPaidLcDollars();

        Schema::table('lc_payments', function (Blueprint $table) {
            $table->dropColumn(['day_rate', 'exchange_gain_loss']);
        });

        $this->addSellPermission();
    }

    /**
     * LC payments already made bought dollars that never left; they are now held in
     * the account each was paid from, at the bank's rate.
     */
    private function keepPaidLcDollars(): void
    {
        $now = now();
        $lcs = DB::table('lcs')->get(['id', 'lc_code', 'type'])->keyBy('id');

        foreach (DB::table('lc_payments')->orderBy('paid_on')->orderBy('id')->get() as $payment) {
            DB::table('dollar_transactions')->insert([
                'payment_account_id' => $payment->payment_account_id,
                'entry_date' => $payment->paid_on,
                'direction' => 'in',
                'source' => 'lc_payment',
                'usd_amount' => $payment->usd_amount,
                'rate' => $payment->bank_rate,
                'bdt_amount' => $payment->bdt_amount,
                'description' => 'Bought with '.strtoupper($lcs[$payment->lc_id]->type ?? 'lc').' payment '.($lcs[$payment->lc_id]->lc_code ?? ''),
                'reference' => $payment->reference,
                'transactionable_type' => LcPayment::class,
                'transactionable_id' => $payment->id,
                'added_by' => $payment->added_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('payment_accounts')->where('id', $payment->payment_account_id)->update([
                'usd_balance' => DB::raw('usd_balance + '.(float) $payment->usd_amount),
                'usd_cost' => DB::raw('usd_cost + '.(float) $payment->bdt_amount),
            ]);
        }
    }

    private function addSellPermission(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'accounts.sell-dollars'],
            ['name' => 'Sell dollars', 'group' => 'Payment Accounts', 'created_at' => $now, 'updated_at' => $now],
        );

        // Whoever records deposits can sell dollars; admins hold every permission.
        $permissionId = DB::table('permissions')->where('key', 'accounts.sell-dollars')->value('id');
        $depositId = DB::table('permissions')->where('key', 'accounts.deposit')->value('id');
        $roleIds = DB::table('role_permission')->where('permission_id', $depositId)->pluck('role_id')
            ->push(DB::table('roles')->where('slug', 'admin')->value('id'))
            ->filter()
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'accounts.sell-dollars')->value('id');
        DB::table('role_permission')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        Schema::table('lc_payments', function (Blueprint $table) {
            $table->decimal('day_rate', 12, 4)->default(0);
            $table->decimal('exchange_gain_loss', 15, 2)->default(0);
        });

        Schema::dropIfExists('dollar_transactions');

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn(['usd_balance', 'usd_cost']);
        });
    }
};
