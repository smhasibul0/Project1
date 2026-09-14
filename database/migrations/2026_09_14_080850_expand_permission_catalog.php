<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace the coarse one-permission-per-module catalog with the detailed catalog in
     * App\Support\PermissionCatalog, and expand every existing role's permissions into the
     * granular keys that grant the same access it had before.
     */
    public function up(): void
    {
        $this->writeCatalog();

        $ids = $this->permissionIds();
        $legacy = PermissionCatalog::legacyMap();

        foreach (DB::table('roles')->get() as $role) {
            if ($role->slug === 'admin') {
                $this->syncRole($role->id, array_values($ids));

                continue;
            }

            $held = DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('role_permission.role_id', $role->id)
                ->pluck('permissions.key')
                ->all();

            $granted = [];

            foreach ($held as $key) {
                // A key that survived into the new catalog is kept as-is; a retired coarse
                // key expands into the granular keys it used to cover.
                $granted = array_merge($granted, $legacy[$key] ?? (isset($ids[$key]) ? [$key] : []));
            }

            // The warehouse portal was gated by the role alone until now, so the warehouse
            // role keeps every screen it could already reach.
            if ($role->slug === 'warehouse') {
                $granted = array_merge($granted, PermissionCatalog::group('Warehouse Portal'));
            }

            // Anyone who could reach the admin panel could see its dashboard.
            if ($granted !== []) {
                $granted[] = 'dashboard.view';
            }

            $this->syncRole($role->id, array_values(array_intersect_key($ids, array_flip($granted))));
        }

        // Retired keys (and any hand-made rows) go once every role has been remapped.
        DB::table('permissions')->whereNotIn('key', PermissionCatalog::keys())->delete();
    }

    /**
     * Collapse the detailed catalog back to the coarse one: a role that holds any granular
     * key of a module gets that module's old "manage" permission.
     */
    public function down(): void
    {
        $legacy = PermissionCatalog::legacyMap();
        $now = now();

        foreach ($legacy as $key => $_) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['name' => $key, 'group' => 'Legacy', 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $ids = $this->permissionIds();

        foreach (DB::table('roles')->get() as $role) {
            $held = DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('role_permission.role_id', $role->id)
                ->pluck('permissions.key')
                ->all();

            $granted = [];

            foreach ($legacy as $coarse => $granular) {
                if (array_intersect($held, $granular) !== []) {
                    $granted[] = $coarse;
                }
            }

            $this->syncRole($role->id, array_values(array_intersect_key($ids, array_flip($granted))));
        }

        DB::table('permissions')->whereNotIn('key', array_keys($legacy))->delete();
    }

    /**
     * Insert every catalog permission and refresh the label and group of the ones that exist.
     */
    private function writeCatalog(): void
    {
        $now = now();
        $existing = DB::table('permissions')->pluck('id', 'key')->all();
        $new = [];

        foreach (PermissionCatalog::all() as $group => $permissions) {
            foreach ($permissions as $key => [$name]) {
                if (isset($existing[$key])) {
                    DB::table('permissions')->where('key', $key)->update(['name' => $name, 'group' => $group, 'updated_at' => $now]);

                    continue;
                }

                $new[] = ['key' => $key, 'name' => $name, 'group' => $group, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (array_chunk($new, 100) as $chunk) {
            DB::table('permissions')->insert($chunk);
        }
    }

    /**
     * @return array<string, int> permission key => id
     */
    private function permissionIds(): array
    {
        return DB::table('permissions')->pluck('id', 'key')->all();
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function syncRole(int $roleId, array $permissionIds): void
    {
        DB::table('role_permission')->where('role_id', $roleId)->delete();

        if ($permissionIds === []) {
            return;
        }

        DB::table('role_permission')->insert(array_map(
            fn (int $permissionId) => ['role_id' => $roleId, 'permission_id' => $permissionId],
            $permissionIds,
        ));
    }
};
