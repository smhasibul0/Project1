<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dollar rate for each day, replacing the single rate kept on company
     * settings. Whatever needs a dollar rate takes the one for its date — or, on a
     * day with none set, the latest before it.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date')->unique();
            // Taka for one US dollar.
            $table->decimal('usd_rate', 12, 4);
            $table->string('note')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        // The single dollar rate becomes the first day of the history.
        $setting = DB::table('company_settings')->first();
        if ($setting && (float) $setting->usd_rate > 0) {
            DB::table('exchange_rates')->insert([
                'rate_date' => $setting->usd_rate_date ?? $now->toDateString(),
                'usd_rate' => $setting->usd_rate,
                'note' => 'Carried over from the single dollar rate',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['usd_rate', 'usd_rate_date']);
        });

        $this->replacePermission($now);
    }

    /**
     * "Set the dollar rate" gives way to the Exchange Rates permissions; whoever held
     * it can now view, add, change and delete daily rates.
     */
    private function replacePermission($now): void
    {
        $permissions = [
            'exchange-rates.view' => 'View exchange rates',
            'exchange-rates.create' => 'Add exchange rates',
            'exchange-rates.edit' => 'Edit exchange rates',
            'exchange-rates.delete' => 'Delete exchange rates',
        ];

        foreach ($permissions as $key => $name) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'group' => 'Exchange Rates', 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $newIds = DB::table('permissions')->whereIn('key', array_keys($permissions))->pluck('id');
        $oldId = DB::table('permissions')->where('key', 'rates.dollar')->value('id');

        $roleIds = DB::table('roles')->where('slug', 'admin')->pluck('id');
        if ($oldId) {
            $roleIds = $roleIds->merge(DB::table('role_permission')->where('permission_id', $oldId)->pluck('role_id'));
        }

        foreach ($roleIds->unique() as $roleId) {
            foreach ($newIds as $permissionId) {
                DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        if ($oldId) {
            DB::table('role_permission')->where('permission_id', $oldId)->delete();
            DB::table('permissions')->where('id', $oldId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('usd_rate', 12, 4)->nullable();
            $table->date('usd_rate_date')->nullable();
        });

        $latest = DB::table('exchange_rates')->orderByDesc('rate_date')->first();
        if ($latest) {
            DB::table('company_settings')->update(['usd_rate' => $latest->usd_rate, 'usd_rate_date' => $latest->rate_date]);
        }

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['key' => 'rates.dollar'],
            ['name' => 'Set the dollar rate', 'group' => 'Rates', 'created_at' => $now, 'updated_at' => $now],
        );
        $ids = DB::table('permissions')->where('group', 'Exchange Rates')->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('exchange_rates');
    }
};
