<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->orderByDesc('is_system')->orderBy('name')->get();

        return view('admin.backend.roles.roles', [
            'roles' => $roles,
            'permissions' => $this->matrix(),
            'permissionCount' => Permission::count(),
        ]);
    }

    /**
     * The permission matrix in catalog order (not alphabetical), each row carrying the id the
     * form posts, the ability key it maps to, and what it lets the role do.
     *
     * @return Collection<string, Collection<int, object>>
     */
    private function matrix()
    {
        $ids = Permission::pluck('id', 'key');

        return collect(PermissionCatalog::all())
            ->map(fn (array $module) => collect($module)
                ->map(fn (array $meta, string $key) => (object) [
                    'id' => $ids[$key] ?? null,
                    'key' => $key,
                    'name' => $meta[0],
                    'description' => $meta[1],
                ])
                ->filter(fn (object $permission) => $permission->id !== null)
                ->values())
            ->filter(fn ($module) => $module->isNotEmpty());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->back()->with('success', 'Role created successfully.');
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        // The Admin role always has every permission (it also bypasses gates), so never
        // let its matrix be narrowed.
        if (! $role->isAdmin()) {
            $role->permissions()->sync($request->input('permissions', []));
        }

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    public function destroy($id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        if ($role->is_system) {
            return redirect()->back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users_count > 0) {
            return redirect()->back()->with('error', 'Reassign this role\'s users before deleting it.');
        }

        $role->delete();

        return redirect()->back()->with('success', 'Role deleted successfully.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
