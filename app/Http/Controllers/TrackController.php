<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Container;
use App\Models\Order;
use App\Models\OrderScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TrackController extends Controller
{
    /**
     * Public order tracking: status timeline only, no financials and no login.
     *
     * The link carries a random token rather than the order number, so it opens
     * one shipment and one only — there is nothing in the URL to edit into
     * somebody else's order, and the tokens can't be guessed in sequence.
     *
     * The same page is what a QR code on a carton opens. A stranger sees the
     * timeline; a signed-in member of staff also sees how far the cartons have
     * got, and a way through to the scanner — counting itself happens there.
     */
    public function show(string $token)
    {
        $order = Order::with(['tracking', 'customer', 'items', 'scans.container', 'scans.scannedBy', 'containers'])
            ->where('track_token', $token)
            ->firstOrFail();

        return view('track', [
            'order' => $order,
            'canScan' => Auth::check() && Gate::allows('orders.scan'),
        ]);
    }

    /**
     * Count cartons through one stage of the journey and move the order with them.
     *
     * Only reachable from the scanner: the key comes from decoding the carton's
     * QR with the camera and is good for one recording, so what is written down
     * always corresponds to somebody standing in front of the goods.
     */
    public function scan(Request $request, string $token)
    {
        $order = Order::with(['items', 'scans'])->where('track_token', $token)->firstOrFail();

        $data = $request->validate([
            'scan_key' => 'required|string',
            'stage' => 'required|in:'.implode(',', array_keys(OrderScan::STAGES)),
            'cartons' => 'required|numeric|min:0.01',
            'container_id' => 'required_if:stage,container_loaded|nullable|exists:containers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'note' => 'nullable|string|max:255',
        ]);

        if (! ScanController::consumeKey($request, $token, $data['scan_key'])) {
            return redirect()->route('scan.index')
                ->with('error', 'Scan the carton again — that code had already been used or had expired.');
        }

        $stage = $data['stage'];
        $status = OrderScan::STAGES[$stage]['status'];
        $cartons = (float) $data['cartons'];

        DB::transaction(function () use ($order, $data, $stage, $status, $cartons) {
            $order->scans()->create([
                'stage' => $stage,
                'cartons' => $cartons,
                'container_id' => $data['container_id'] ?? null,
                'note' => $data['note'] ?? null,
                'scanned_by' => Auth::id(),
            ]);

            // Loading cartons in also puts the order in that container, carrying
            // the carton count onto the pivot the cost split is worked out from.
            if ($stage === 'container_loaded' && $data['container_id']) {
                $this->loadIntoContainer($order, (int) $data['container_id']);
            }

            $updates = ['goods_status' => $status];
            $today = now()->toDateString();

            if ($stage === 'at_port' && ! $order->port_arrival_date) {
                $updates['port_arrival_date'] = $today;
            }

            if ($stage === 'bd_warehouse') {
                $updates['warehouse_id'] = $data['warehouse_id'] ?? $order->warehouse_id;
                if (! $order->bd_warehouse_date) {
                    $updates['bd_warehouse_date'] = $today;
                }
            }

            $order->update($updates);

            if ($stage === 'bd_warehouse' && $order->warehouse_id) {
                $order->fresh('items')->receiveIntoWarehouse((int) $order->warehouse_id, Auth::id());
            }

            $counted = $order->fresh()->load('scans')->cartonsScannedAt($stage);
            $note = trim(sprintf(
                '%s carton(s) scanned — %s of %s. %s',
                rtrim(rtrim(number_format($cartons, 2), '0'), '.'),
                rtrim(rtrim(number_format($counted, 2), '0'), '.'),
                rtrim(rtrim(number_format($order->totalCartons(), 2), '0'), '.'),
                $data['note'] ?? ''
            ));

            $order->logStatus($status, $note, Auth::id());
        });

        return redirect()->route('scan.index')->with('success', sprintf(
            '%s · %s carton(s) — %s.',
            $order->order_no,
            rtrim(rtrim(number_format($cartons, 2), '0'), '.'),
            OrderScan::STAGES[$stage]['label'],
        ));
    }

    /**
     * Put the order in the container, adding to the cartons already recorded for
     * it there rather than replacing them.
     */
    private function loadIntoContainer(Order $order, int $containerId): void
    {
        $container = Container::findOrFail($containerId);
        $existing = $container->orders()->where('orders.id', $order->id)->first();

        $cartons = round($order->fresh()->load('scans')->cartonsScannedAt('container_loaded'), 2);
        $pivot = [
            'ctn' => $cartons,
            'weight' => round((float) $order->items->sum('net_weight'), 2),
            'cbm' => round((float) $order->items->sum('cbm'), 4),
        ];

        $existing
            ? $container->orders()->updateExistingPivot($order->id, $pivot)
            : $container->orders()->attach($order->id, $pivot);

        if (! $existing) {
            ActivityLog::record($order, ActivityLog::EDITED, 'Loaded into container '.$container->container_code.' by carton scan', parent: $container);
        }

        // The container's costs are split by what's loaded, so the shares move.
        $container->load('orders');
        foreach ($container->orders as $member) {
            $member->recomputeFinancials();
        }
    }
}
