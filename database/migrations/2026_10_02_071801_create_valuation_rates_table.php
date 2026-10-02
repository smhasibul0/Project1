<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reference declared value per HS code, read from the Customs valuation
     * reports uploaded under Rates & Taxes → Rates. Each upload is kept under the
     * date it was given; quotations use the latest one for a code.
     */
    public function up(): void
    {
        Schema::create('valuation_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date')->index();
            $table->string('code_digits', 12)->index();
            $table->string('hs_code', 20);
            $table->string('description')->nullable();
            // The highest unit price Customs assessed, in US dollars per kilogram.
            $table->decimal('unit_price', 14, 4);
            $table->unsignedInteger('bills_count')->default(0);
            $table->json('bills')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $permissions = [
            'rates.view' => 'View rates',
            'rates.upload' => 'Upload rates',
            'rates.delete' => 'Delete rates',
            'rates.dollar' => 'Set the dollar rate',
        ];

        foreach ($permissions as $key => $name) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'group' => 'Rates', 'created_at' => $now, 'updated_at' => $now],
            );
        }

        // Admins hold every permission in the matrix; other roles are granted on the Roles screen.
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId) {
            foreach (DB::table('permissions')->whereIn('key', array_keys($permissions))->pluck('id') as $permissionId) {
                DB::table('role_permission')->insertOrIgnore(['role_id' => $adminRoleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('group', 'Rates')->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('valuation_rates');
    }
};
