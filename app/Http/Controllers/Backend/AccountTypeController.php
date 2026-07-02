<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountType;

class AccountTypeController extends Controller
{

    // Store Account Type
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        AccountType::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Account Type Added Successfully');
    }



    // Edit Account Type
    public function edit($id)
    {
        $accountType = AccountType::findOrFail($id);

        return view('admin.backend.accounts.edit_account_type', compact('accountType'));
    }



    // Update Account Type
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $accountType = AccountType::findOrFail($id);

        $accountType->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('payment.accounts')->with('success', 'Account Type Updated Successfully');
    }



    // Delete Account Type
    public function destroy($id)
    {
        $accountType = AccountType::findOrFail($id);

        $accountType->delete();

        return redirect()->back()->with('success', 'Account Type Deleted Successfully');
    }

}