<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\AccountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentAccountController extends Controller
{
    public function index()
    {
        $accounts     = PaymentAccount::with(['accountType', 'addedBy'])->where('is_active', 1)->paginate(100);
        $accountTypes = AccountType::all();

        return view('admin.backend.payment_accounts.payment_accounts', compact('accounts', 'accountTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'account_type_id' => 'required|exists:account_types,id',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        PaymentAccount::create([
            'name'            => $request->name,
            'account_type_id' => $request->account_type_id,
            'account_number'  => $request->account_number,
            'account_details' => $request->account_details,
            'balance'         => $request->opening_balance ?? 0,
            'note'            => $request->note,
            'is_active'       => $request->has('is_active') ? 1 : 0,
            'added_by'        => Auth::id(),
        ]);

        return redirect()->route('payment.accounts')->with('success', 'Account created successfully.');
    }

    public function close($id)
    {
        PaymentAccount::findOrFail($id)->update(['is_active' => 0]);
        return back()->with('success', 'Account closed.');
    }

    public function book(Request $request, $id)
    {
        $account = PaymentAccount::with(['accountType', 'addedBy'])->findOrFail($id);

        $query = $account->transactions()->with('addedBy')->latest();

        // Date range filter
        if ($request->date_range) {
            [$from, $to] = explode(' - ', $request->date_range);
            $query->whereBetween('created_at', [
                Carbon::createFromFormat('d-m-Y', trim($from))->startOfDay(),
                Carbon::createFromFormat('d-m-Y', trim($to))->endOfDay(),
            ]);
        }

        // Transaction type filter
        if ($request->transaction_type && $request->transaction_type !== 'all') {
            $query->where('type', $request->transaction_type);
        }

        $transactions = $query->get();

        return view('admin.payment_accounts.book', compact('account', 'transactions'));
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'account_id'   => 'required|exists:payment_accounts,id',
            'amount'       => 'required|numeric|min:0.01',
            'deposit_date' => 'required|date',
        ]);

        $account = PaymentAccount::findOrFail($request->account_id);
        $account->increment('balance', $request->amount);

        // Log transaction
        $account->transactions()->create([
            'type'            => 'credit',
            'credit'          => $request->amount,
            'debit'           => 0,
            'running_balance' => $account->fresh()->balance,
            'description'     => 'Deposit',
            'payment_method'  => $request->deposit_from,
            'note'            => $request->note,
            'added_by'        => Auth::id(),
            'created_at'      => $request->deposit_date,
        ]);

        return back()->with('success', 'Deposit successful.');
    }

    public function fundTransfer(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|exists:payment_accounts,id',
            'to_account_id'   => 'required|exists:payment_accounts,id|different:from_account_id',
            'amount'          => 'required|numeric|min:0.01',
            'transfer_date'   => 'required|date',
            'document'        => 'nullable|file|max:5120|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png',
        ]);

        $docPath = null;
        if ($request->hasFile('document')) {
            $docPath = $request->file('document')->store('fund_transfers', 'public');
        }

        DB::transaction(function () use ($request, $docPath) {
            $from = PaymentAccount::lockForUpdate()->findOrFail($request->from_account_id);
            $to   = PaymentAccount::lockForUpdate()->findOrFail($request->to_account_id);

            $from->decrement('balance', $request->amount);
            $to->increment('balance', $request->amount);

            // Optionally log to a fund_transfers table:
            // FundTransfer::create([
            //     'from_account_id' => $from->id,
            //     'to_account_id'   => $to->id,
            //     'amount'          => $request->amount,
            //     'transfer_date'   => $request->transfer_date,
            //     'note'            => $request->note,
            //     'document'        => $docPath,
            //     'added_by'        => Auth::id(),
            // ]);
        });

        return back()->with('success', 'Fund transferred successfully.');
    }
}