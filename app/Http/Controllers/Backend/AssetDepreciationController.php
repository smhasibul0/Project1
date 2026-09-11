<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetDepreciationController extends Controller
{
    /**
     * One month of depreciation: what has been posted, and which assets are
     * still waiting to be charged for it.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));
        $period = $month->copy()->startOfMonth();

        $posted = AssetDepreciation::with('asset.category')
            ->whereDate('period', $period)
            ->get()
            ->sortBy(fn (AssetDepreciation $d) => $d->asset?->asset_code);

        $pending = $this->pendingFor($period);

        $assets = Asset::with('depreciations')->onBooks()->orderBy('asset_code')->get();

        return view('admin.backend.assets.depreciation', [
            'month' => $month,
            'posted' => $posted,
            'postedTotal' => round($posted->sum(fn (AssetDepreciation $d) => (float) $d->amount), 2),
            'pending' => $pending,
            'pendingTotal' => round($pending->sum(fn (Asset $a) => $a->monthlyCharge($period)), 2),
            'period' => $period,
            'yearToDate' => round((float) AssetDepreciation::whereBetween('period', [
                $month->copy()->startOfYear(), $month->copy()->endOfMonth(),
            ])->sum('amount'), 2),
            'bookValue' => round($assets->sum(fn (Asset $a) => $a->bookValue()), 2),
            'fullyDepreciated' => $assets->filter(fn (Asset $a) => $a->isFullyDepreciated())->count(),
        ]);
    }

    /**
     * Post one month's depreciation across every asset still on the books.
     * Re-running is safe: an asset already charged for the month is skipped
     * rather than charged twice, and an asset written down to its salvage
     * value drops out on its own.
     */
    public function generate(Request $request)
    {
        $period = $this->resolveMonth($request->input('month'))->startOfMonth();
        $pending = $this->pendingFor($period);

        if ($pending->isEmpty()) {
            return redirect()->back()->with('success', 'Nothing to post — every asset is already charged for '.$period->format('F Y').'.');
        }

        DB::transaction(function () use ($pending, $period) {
            foreach ($pending as $asset) {
                $amount = $asset->monthlyCharge($period);

                $asset->depreciations()->create([
                    'period' => $period,
                    'amount' => $amount,
                    'book_value_after' => round($asset->bookValue() - $amount, 2),
                    'added_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', $pending->count().' asset(s) depreciated for '.$period->format('F Y').'.');
    }

    /**
     * Reverse a single posted month, putting the book value back.
     */
    public function destroy($id)
    {
        AssetDepreciation::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Depreciation entry reversed.');
    }

    /**
     * Reverse a whole month in one go, for a run posted by mistake.
     */
    public function destroyMonth(Request $request)
    {
        $period = $this->resolveMonth($request->input('month'))->startOfMonth();
        $removed = AssetDepreciation::whereDate('period', $period)->delete();

        return redirect()->back()->with('success', $removed.' entr(ies) reversed for '.$period->format('F Y').'.');
    }

    /**
     * Assets on the books that still have a charge to take for this month and
     * have not been charged for it yet.
     *
     * @return Collection<int, Asset>
     */
    private function pendingFor(Carbon $period)
    {
        $alreadyPosted = AssetDepreciation::whereDate('period', $period)->pluck('asset_id')->all();

        return Asset::with('depreciations')
            ->onBooks()
            ->whereNotIn('id', $alreadyPosted)
            ->orderBy('asset_code')
            ->get()
            ->filter(fn (Asset $asset) => $asset->isDepreciableFor($period))
            ->values();
    }

    /**
     * The month being viewed, defaulting to the current one. Anything
     * unparseable falls back rather than erroring the page.
     */
    private function resolveMonth(?string $month): Carbon
    {
        try {
            return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Exception) {
            return Carbon::now()->startOfMonth();
        }
    }
}
