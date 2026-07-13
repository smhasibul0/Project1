<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'contact', 'warehouse'])->latest()->get();
        $roles = Role::orderBy('name')->get();
        // Customer logins must be linked to a customer contact; warehouse logins to a warehouse.
        $customerContacts = Contact::customers()->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('admin.backend.users.users', compact('users', 'roles', 'customerContacts', 'warehouses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + $this->contactRules($request) + $this->warehouseRules($request));
        $data['contact_id'] = $this->isCustomerRole($data['role_id']) ? (int) $request->contact_id : null;
        $data['warehouse_id'] = $this->isWarehouseRole($data['role_id']) ? (int) $request->warehouse_id : null;
        $data['status'] = $request->has('is_active') ? 'active' : 'inactive';
        $data['password'] = Hash::make($request->password);

        User::create($data);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate($this->rules($user->id) + $this->contactRules($request, $user->id) + $this->warehouseRules($request));
        $data['contact_id'] = $this->isCustomerRole($data['role_id']) ? (int) $request->contact_id : null;
        $data['warehouse_id'] = $this->isWarehouseRole($data['role_id']) ? (int) $request->warehouse_id : null;
        $data['status'] = $request->has('is_active') ? 'active' : 'inactive';

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function toggleActive($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);

        return redirect()->back()->with('success', 'User '.$user->status.'.');
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    private function isCustomerRole(int|string|null $roleId): bool
    {
        return $roleId !== null && Role::whereKey($roleId)->where('slug', 'customer')->exists();
    }

    private function isWarehouseRole(int|string|null $roleId): bool
    {
        return $roleId !== null && Role::whereKey($roleId)->where('slug', 'warehouse')->exists();
    }

    /**
     * When the chosen role is warehouse, a warehouse must be linked.
     *
     * @return array<string, mixed>
     */
    private function warehouseRules(Request $request): array
    {
        if (! $this->isWarehouseRole($request->input('role_id'))) {
            return [];
        }

        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ];
    }

    /**
     * When the chosen role is customer, a unique customer contact must be linked.
     *
     * @return array<string, mixed>
     */
    private function contactRules(Request $request, ?int $ignoreId = null): array
    {
        if (! $this->isCustomerRole($request->input('role_id'))) {
            return [];
        }

        return [
            'contact_id' => ['required', 'exists:contacts,id', Rule::unique('users', 'contact_id')->ignore($ignoreId)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($ignoreId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'phone' => 'nullable|string|max:50',
            'role_id' => 'required|exists:roles,id',
            'password' => $ignoreId ? 'nullable|string|min:8' : 'required|string|min:8',
        ];
    }
}
