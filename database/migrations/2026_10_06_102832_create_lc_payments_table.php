<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dollar payments against an LC, in one go or in parts. Each records the day's
     * rate and the rate the bank actually charged: the taka that left the account is
     * dollars × bank rate, and the difference from the day's rate is the exchange gain
     * or loss. The payment itself is never a cost.
     */
    public function up(): void
    {
        Schema::create('lc_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lc_id')->constrained('lcs')->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained('payment_accounts');
            $table->date('paid_on')->index();
            $table->decimal('usd_amount', 15, 2);
            // Taka for one dollar: the day's rate, and what the bank charged.
            $table->decimal('day_rate', 12, 4);
            $table->decimal('bank_rate', 12, 4);
            // Dollars × bank rate — what left the account.
            $table->decimal('bdt_amount', 15, 2);
            // Dollars × (day's rate − bank rate): negative is a loss.
            $table->decimal('exchange_gain_loss', 15, 2)->default(0);
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $permissions = [
            'lc.payments.create' => ['Pay LCs', 'lc.costs.create'],
            'lc.payments.delete' => ['Reverse LC payments', 'lc.costs.delete'],
        ];

        foreach ($permissions as $key => [$name, $alongside]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'group' => 'Letters of Credit', 'created_at' => $now, 'updated_at' => $now],
            );

            // Granted to Admin and to whoever already posts or removes LC charges.
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            $alongsideId = DB::table('permissions')->where('key', $alongside)->value('id');
            $roleIds = DB::table('roles')->where('slug', 'admin')->pluck('id')
                ->merge($alongsideId ? DB::table('role_permission')->where('permission_id', $alongsideId)->pluck('role_id') : [])
                ->unique();

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', ['lc.payments.create', 'lc.payments.delete'])->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('lc_payments');
    }
};
