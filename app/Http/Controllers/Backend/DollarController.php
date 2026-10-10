<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DollarTransaction;
use App\Models\PaymentAccount;
use App\Support\DollarLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What is done with the dollars an account holds, on the account itself: dollars
 * paid in, sold for taka, or moved to another account. Dollars bought with an LC
 * payment, and dollars spent on costs, come and go from those records instead.
 */
class DollarController extends Controller
{
    /**
     * Dollars paid into the account from outside, costing the rate given.
     */
    public function deposit(Request $request, $id): RedirectResponse
    {
        $account = PaymentAccount::findOrFail($id);
        $data = $request->validate([
            'usd_amount' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0.0001|max:100000',
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(fn () => DollarLedger::receive($account, (float) $data['usd_amount'], (float) $data['rate'], 'deposit', $data + [
            'description' => 'Dollars deposited',
        ]));

        return redirect()->back()->with('success', '$'.number_format((float) $data['usd_amount'], 2).' added to '.$account->name
            .' at ৳'.DollarTransaction::rate($data['rate']).'.');
    }

    /**
     * Sell dollars for taka. The taka comes into the same account; the gain or loss
     * against what the dollars cost goes to the P&L.
     */
    public function sell(Request $request, $id): RedirectResponse
    {
        $account = PaymentAccount::findOrFail($id);
        $data = $request->validate([
            'usd_amount' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0.0001|max:100000',
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);

        $sale = DB::transaction(fn () => DollarLedger::sell($account, (float) $data['usd_amount'], (float) $data['rate'], $data));
        $result = (float) $sale->gain_loss;

        return redirect()->back()->with('success', 'Sold $'.number_format((float) $sale->usd_amount, 2).' for ৳'.number_format($sale->proceeds(), 2)
            .($result == 0 ? ' — no exchange gain or loss.' : ' — exchange '.($result > 0 ? 'gain' : 'loss').' ৳'.number_format(abs($result), 2).'.'));
    }

    /**
     * Move dollars to another account, at what they cost.
     */
    public function transfer(Request $request, $id): RedirectResponse
    {
        $account = PaymentAccount::findOrFail($id);
        $data = $request->validate([
            'to_account_id' => 'required|exists:payment_accounts,id|not_in:'.$account->id,
            'usd_amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ], ['to_account_id.not_in' => 'Choose another account to move the dollars to.']);

        $to = PaymentAccount::findOrFail($data['to_account_id']);
        DB::transaction(fn () => DollarLedger::transfer($account, $to, (float) $data['usd_amount'], $data));

        return redirect()->back()->with('success', '$'.number_format((float) $data['usd_amount'], 2).' moved from '.$account->name.' to '.$to->name.'.');
    }

    /**
     * Undo a deposit, sale or transfer made on the account. Dollars bought or spent
     * through a record are undone from that record.
     */
    public function destroy($id): RedirectResponse
    {
        $entry = DollarTransaction::findOrFail($id);

        if (! $entry->isManual()) {
            return redirect()->back()->with('error', 'This entry belongs to a '.strtolower($entry->sourceLabel()).' — undo it from there.');
        }

        DB::transaction(fn () => DollarLedger::reverse($entry));

        return redirect()->back()->with('success', $entry->sourceLabel().' of $'.number_format((float) $entry->usd_amount, 2).' reversed.');
    }
}
