<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Supplier directory (type supplier or both).
     */
    public function suppliers()
    {
        $contacts = Contact::suppliers()->with('addedBy')->latest()->get();

        return view('admin.backend.contacts.contacts', [
            'contacts' => $contacts,
            'type' => 'supplier',
            'pageTitle' => 'Suppliers',
            'customerGroups' => collect(),
        ]);
    }

    /**
     * Customer directory (type customer or both).
     */
    public function customers()
    {
        $contacts = Contact::customers()->with(['addedBy', 'customerGroup'])->latest()->get();

        return view('admin.backend.contacts.contacts', [
            'contacts' => $contacts,
            'type' => 'customer',
            'pageTitle' => 'Customers',
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

        return redirect()->back()->with('success', ucfirst($request->type).' created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        $data = $this->validated($request);
        $data['name'] = $data['name'] ?? $data['business_name'];
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $data['opening_balance'] = $request->opening_balance ?? 0;

        $contact->update($data);

        return redirect()->back()->with('success', 'Contact updated successfully.');
    }

    public function destroy($id)
    {
        Contact::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Contact deleted successfully.');
    }

    public function toggleActive($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->update(['is_active' => ! $contact->is_active]);

        return redirect()->back()->with('success', 'Contact '.($contact->is_active ? 'activated' : 'deactivated').' successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => 'required|in:supplier,customer,both',
            // A contact needs at least one label: a person name or a business name.
            'name' => 'nullable|string|max:255|required_without:business_name',
            'business_name' => 'nullable|string|max:255|required_without:name',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:50',
            'alternate_contact' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'bank_details' => 'nullable|string',
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
            'warehouse_address' => 'nullable|string',
            'shipping_mark' => 'nullable|string|max:255',
            'more_information' => 'nullable|string',
            'lead_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
    }
}
