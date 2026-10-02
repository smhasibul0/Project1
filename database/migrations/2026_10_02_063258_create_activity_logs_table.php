<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who added, edited, updated the status of, or deleted every record — and what
     * changed. The record's label and the user's name are copied onto each row so the
     * history still reads after either of them is deleted.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 30);
            $table->nullableMorphs('subject');
            $table->string('subject_label')->nullable();
            // The record a line item belongs to (a payment's order), so the order's
            // history shows its payments, costs and scans too.
            $table->nullableMorphs('parent');
            $table->string('description')->nullable();
            $table->json('field_changes')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'activity.view'],
            ['name' => 'View activity log', 'group' => 'Activity Log', 'created_at' => $now, 'updated_at' => $now],
        );

        // Admins hold every permission in the matrix; nobody else sees the log until granted.
        $permissionId = DB::table('permissions')->where('key', 'activity.view')->value('id');
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $adminRoleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'activity.view')->value('id');

        if ($permissionId) {
            DB::table('role_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('activity_logs');
    }
};
