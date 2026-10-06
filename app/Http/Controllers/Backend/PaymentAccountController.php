<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentAccountController extends Controller
{
    public function index()
    {
        $accounts = PaymentAccount::with(['accountType', 'addedBy'])
            ->withSum(['transactions as usd_sent' => fn ($query) => $query->where('type', 'debit')], 'usd_amount')
            ->latest()
            ->get();
        $accountTypes = AccountType::all();

        return view('admin.backend.payment_accounts.payment_accounts', compact('accounts', 'accountTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'account_type_id' => 'required|exists:account_types,id',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        PaymentAccount::create([
            'name' => $request->name,
            'account_type_id' => $request->account_type_id,
            'account_number' => $request->account_number,
            'account_details' => $request->account_details,
            'balance' => $request->opening_balance ?? 0,
            'note' => $request->note,
            'is_active' => $request->has('is_active') ? 1 : 0,
            'added_by' => Auth::id(),
        ]);

        return redirect()->route('payment.accounts')->with('success', 'Account created successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'account_type_id' => 'required|exists:account_types,id',
        ]);

        $account = PaymentAccount::findOrFail($id);

        $account->update([
            'name' => $request->name,
            'account_type_id' => $request->account_type_id,
            'account_number' => $request->account_number,
            'account_details' => $request->account_details,
            'note' => $request->note,
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('payment.accounts')->with('success', 'Account updated successfully.');
    }

    public function toggleActive($id)
    {
        $account = PaymentAccount::findOrFail($id);
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', 'Account '.($account->is_active ? 'activated' : 'deactivated').'.');
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

        // Pre-select the from/to accounts of each fund transfer in the edit modal.
        Transaction::attachTransferAccounts($transactions);

        // Accounts available as transfer source/destination in the edit modal.
        $accounts = PaymentAccount::where('is_active', 1)->orderBy('name')->get();

        return view('admin.backend.payment_accounts.account_book', compact('account', 'transactions', 'accounts'));
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:payment_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'deposit_date' => 'required|date',
        ]);

        $account = PaymentAccount::findOrFail($request->account_id);
        $account->increment('balance', $request->amount);

        // Log transaction
        $account->transactions()->create([
            'type' => 'credit',
            'source' => 'deposit',
            'amount' => $request->amount,
            'credit' => $request->amount,
            'debit' => 0,
            'running_balance' => $account->fresh()->balance,
            'description' => 'Deposit',
            'payment_method' => $request->deposit_from,
            'note' => $request->note,
            'added_by' => Auth::id(),
            'created_at' => $request->deposit_date,
        ]);

        return back()->with('success', 'Deposit successful.');
    }

    public function fundTransfer(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|exists:payment_accounts,id',
            'to_account_id' => 'required|exists:payment_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'document' => 'nullable|file|max:5120|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png',
        ]);

        DB::transaction(function () use ($request) {
            $from = PaymentAccount::lockForUpdate()->findOrFail($request->from_account_id);
            $to = PaymentAccount::lockForUpdate()->findOrFail($request->to_account_id);

            // Sufficient-balance guard: the source must be able to cover the transfer.
            $this->guardNonNegative($from, (float) $from->balance - (float) $request->amount);

            $docPath = null;
            if ($request->hasFile('document')) {
                $docPath = $request->file('document')->store('fund_transfers', 'public');
            }

            $from->decrement('balance', $request->amount);
            $to->increment('balance', $request->amount);

            // Shared id so the two legs can be edited/deleted together later.
            $group = (string) Str::uuid();

            // Ledger: debit the source account …
            $from->transactions()->create([
                'type' => 'debit',
                'source' => 'fund_transfer',
                'transfer_group' => $group,
                'amount' => $request->amount,
                'debit' => $request->amount,
                'running_balance' => $from->fresh()->balance,
                'description' => 'Fund transfer to '.$to->name,
                'payment_details' => $docPath,
                'note' => $request->note,
                'added_by' => Auth::id(),
                'created_at' => $request->transfer_date,
            ]);

            // … and credit the destination account.
            $to->transactions()->create([
                'type' => 'credit',
                'source' => 'fund_transfer',
                'transfer_group' => $group,
                'amount' => $request->amount,
                'credit' => $request->amount,
                'running_balance' => $to->fresh()->balance,
                'description' => 'Fund transfer from '.$from->name,
                'payment_details' => $docPath,
                'note' => $request->note,
                'added_by' => Auth::id(),
                'created_at' => $request->transfer_date,
            ]);
        });

        return back()->with('success', 'Fund transferred successfully.');
    }

    /**
     * Update a deposit or fund-transfer transaction from the account book.
     */
    public function transactionUpdate(Request $request, $id)
    {
        $txn = Transaction::findOrFail($id);

        if (! $txn->isManual()) {
            return back()->with('error', 'This transaction cannot be edited from the account book.');
        }

        return $txn->source === 'fund_transfer'
            ? $this->updateFundTransfer($request, $txn)
            : $this->updateDeposit($request, $txn);
    }

    /**
     * Update a deposit's amount / date / note / method and re-sync its account.
     */
    private function updateDeposit(Request $request, Transaction $txn)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        $newAmount = round((float) $validated['amount'], 2);

        DB::transaction(function () use ($txn, $validated, $newAmount) {
            $leg = Transaction::whereKey($txn->id)->lockForUpdate()->firstOrFail();
            $account = PaymentAccount::lockForUpdate()->findOrFail($leg->payment_account_id);

            $oldEffect = (float) $leg->credit - (float) $leg->debit;
            $resulting = round((float) $account->balance - $oldEffect + $newAmount, 2);

            // Reducing a deposit must not overdraw the account (funds already spent).
            $this->guardNonNegative($account, $resulting);

            $leg->amount = $newAmount;
            $leg->credit = $newAmount;
            $leg->debit = 0;
            $leg->note = $validated['note'] ?? null;
            $leg->payment_method = $validated['payment_method'] ?? $leg->payment_method;
            $leg->created_at = $validated['date'];
            $leg->save();

            $account->balance = $resulting;
            $account->save();

            $this->resyncRunningBalances($account);
        });

        return back()->with('success', 'Transaction updated successfully.');
    }

    /**
     * Update a fund transfer — amount, date, note, and optionally the source/destination
     * accounts. Balances are reversed on the old accounts and applied to the new ones, then
     * every affected account's running balances are recomputed.
     */
    private function updateFundTransfer(Request $request, Transaction $txn)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:payment_accounts,id',
            'to_account_id' => 'required|exists:payment_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $newAmount = round((float) $validated['amount'], 2);
        $newFromId = (int) $validated['from_account_id'];
        $newToId = (int) $validated['to_account_id'];

        DB::transaction(function () use ($txn, $validated, $newAmount, $newFromId, $newToId) {
            $legs = Transaction::where('transfer_group', $txn->transfer_group)->lockForUpdate()->get();
            $debitLeg = $legs->firstWhere('type', 'debit');
            $creditLeg = $legs->firstWhere('type', 'credit');

            // Lock every affected account (old + new) once, keyed by id, so overlaps are safe.
            $ids = array_unique([$debitLeg->payment_account_id, $creditLeg->payment_account_id, $newFromId, $newToId]);
            $accounts = PaymentAccount::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            // Reverse the old effect on the old accounts.
            $accounts[$debitLeg->payment_account_id]->balance = (float) $accounts[$debitLeg->payment_account_id]->balance + (float) $debitLeg->debit;
            $accounts[$creditLeg->payment_account_id]->balance = (float) $accounts[$creditLeg->payment_account_id]->balance - (float) $creditLeg->credit;

            $newFrom = $accounts[$newFromId];
            $newTo = $accounts[$newToId];

            // Sufficient-balance guard: the (possibly new) source, after reversing the old
            // transfer, must be able to cover the new amount.
            $this->guardNonNegative($newFrom, (float) $newFrom->balance - $newAmount);

            // Move and rewrite both legs onto the (possibly new) accounts.
            $debitLeg->payment_account_id = $newFromId;
            $debitLeg->amount = $newAmount;
            $debitLeg->debit = $newAmount;
            $debitLeg->credit = 0;
            $debitLeg->description = 'Fund transfer to '.$newTo->name;
            $debitLeg->note = $validated['note'] ?? null;
            $debitLeg->created_at = $validated['date'];
            $debitLeg->save();

            $creditLeg->payment_account_id = $newToId;
            $creditLeg->amount = $newAmount;
            $creditLeg->credit = $newAmount;
            $creditLeg->debit = 0;
            $creditLeg->description = 'Fund transfer from '.$newFrom->name;
            $creditLeg->note = $validated['note'] ?? null;
            $creditLeg->created_at = $validated['date'];
            $creditLeg->save();

            // Apply the new effect.
            $newFrom->balance = (float) $newFrom->balance - $newAmount;
            $newTo->balance = (float) $newTo->balance + $newAmount;

            foreach ($accounts as $account) {
                $account->balance = round((float) $account->balance, 2);
                $account->save();
                $this->resyncRunningBalances($account);
            }
        });

        return back()->with('success', 'Fund transfer updated successfully.');
    }

    /**
     * Delete a deposit or fund-transfer transaction, reversing its effect on the account
     * balance(s). Deleting one leg of a transfer removes both legs.
     */
    public function transactionDestroy($id)
    {
        $txn = Transaction::findOrFail($id);

        if (! $txn->isManual()) {
            return back()->with('error', 'This transaction cannot be deleted from the account book.');
        }

        DB::transaction(function () use ($txn) {
            $legs = $txn->source === 'fund_transfer' && $txn->transfer_group
                ? Transaction::where('transfer_group', $txn->transfer_group)->lockForUpdate()->get()
                : collect([Transaction::whereKey($txn->id)->lockForUpdate()->first()]);

            foreach ($legs as $leg) {
                $account = PaymentAccount::lockForUpdate()->findOrFail($leg->payment_account_id);

                // Reverse the leg's effect: remove its credit, add back its debit.
                $resulting = round((float) $account->balance - (float) $leg->credit + (float) $leg->debit, 2);

                // Reversing a credit must not overdraw the account (funds already spent).
                $this->guardNonNegative($account, $resulting);

                $account->balance = $resulting;
                $account->save();

                $leg->delete();

                $this->resyncRunningBalances($account);
            }
        });

        return back()->with('success', 'Transaction deleted successfully.');
    }

    /**
     * Guard that an account's resulting balance is not negative, aborting (and rolling back)
     * the surrounding transaction with a user-facing validation error if it would be.
     */
    private function guardNonNegative(PaymentAccount $account, float $resultingBalance): void
    {
        if (round($resultingBalance, 2) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient balance in "'.$account->name.'": available ৳'.number_format((float) $account->balance, 2).'.',
            ]);
        }
    }

    /**
     * Recompute the stored running_balance of every transaction on an account, in
     * chronological order, from the account's invariant opening baseline. Call this after
     * the account balance and transaction rows have been brought to a consistent state.
     */
    private function resyncRunningBalances(PaymentAccount $account): void
    {
        $transactions = $account->transactions()->orderBy('created_at')->orderBy('id')->get();

        $net = (float) $transactions->sum('credit') - (float) $transactions->sum('debit');
        $running = round((float) $account->balance - $net, 2); // opening baseline

        foreach ($transactions as $transaction) {
            $running = round($running + (float) $transaction->credit - (float) $transaction->debit, 2);
            $transaction->running_balance = $running;
            $transaction->save();
        }
    }
}
