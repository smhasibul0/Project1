<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('role')->latest()->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.backend.users.users', compact('users', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['status'] = $request->has('is_active') ? 'active' : 'inactive';
        $data['password'] = Hash::make($request->password);

        User::create($data);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate($this->rules($user->id));
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
