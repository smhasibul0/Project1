<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ContactController extends Controller
{
    /**
     * The customer directory.
     */
    public function customers()
    {
        $contacts = Contact::with(['addedBy', 'customerGroup', 'user', 'quotations'])->latest()->get();

        return view('admin.backend.customers.customers', [
            'contacts' => $contacts,
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['name'] = $data['name'] ?? $data['business_name'];
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $data['opening_balance'] = $request->opening_balance ?? 0;
        $data['added_by'] = Auth::id();

        Contact::create($data);

        return redirect()->back()->with('success', 'Customer created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        $data = $this->validated($request);
        $data['name'] = $data['name'] ?? $data['business_name'];
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $data['opening_balance'] = $request->opening_balance ?? 0;

        $contact->update($data);

        return redirect()->back()->with('success', 'Customer updated successfully.');
    }

    public function destroy($id)
    {
        Contact::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Customer deleted successfully.');
    }

    public function toggleActive($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->update(['is_active' => ! $contact->is_active]);

        return redirect()->back()->with('success', 'Customer '.($contact->is_active ? 'activated' : 'deactivated').' successfully.');
    }

    /**
     * Create a customer portal login for a contact (one login per customer).
     */
    public function createLogin(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        if ($contact->user) {
            return redirect()->back()->with('error', 'This customer already has a login.');
        }

        $data = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $role = Role::where('slug', 'customer')->first();

        User::create([
            'first_name' => $contact->name ?: $contact->business_name ?: 'Customer',
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $role?->id,
            'contact_id' => $contact->id,
        ]);

        return redirect()->back()->with('success', 'Customer login created for '.($contact->name ?: $contact->business_name).'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            // A customer needs at least one label: a person name or a business name.
            'name' => 'nullable|string|max:255|required_without:business_name',
            'business_name' => 'nullable|string|max:255|required_without:name',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:50',
            'alternate_contact' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'pay_term_number' => 'nullable|integer|min:0',
            'pay_term_type' => 'nullable|in:days,months',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255',
            'more_information' => 'nullable|string',
            'lead_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
    }
}
