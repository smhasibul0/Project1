<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerGroupController extends Controller
{
    public function index()
    {
        $groups = CustomerGroup::withCount('contacts')->latest()->get();

        return view('admin.backend.customer_groups.customer_groups', compact('groups'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['discount_amount'] = $request->discount_amount ?? 0;
        $data['added_by'] = Auth::id();

        CustomerGroup::create($data);

        return redirect()->back()->with('success', 'Customer group created successfully.');
    }

    public function update(Request $request, $id)
    {
        $group = CustomerGroup::findOrFail($id);

        $data = $this->validated($request);
        $data['discount_amount'] = $request->discount_amount ?? 0;

        $group->update($data);

        return redirect()->back()->with('success', 'Customer group updated successfully.');
    }

    public function destroy($id)
    {
        CustomerGroup::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Customer group deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);
    }
}
