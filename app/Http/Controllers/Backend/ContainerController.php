<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Container;
use App\Models\ContainerCost;
use App\Models\ContainerDocument;
use App\Models\CostCategory;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\TransportationMode;
use App\Support\DollarLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContainerController extends Controller
{
    public function index()
    {
        $containers = Container::withCount('orders')->latest()->get();

        return view('admin.backend.containers.containers', compact('containers'));
    }

    public function create()
    {
        return view('admin.backend.containers.create', array_merge($this->formData(), ['container' => null]));
    }

    public function store(Request $request)
    {
        $container = Container::create($this->validated($request) + ['added_by' => Auth::id()]);

        return redirect()->route('container.show', $container->id)->with('success', 'Container created — now assign orders.');
    }

    public function show($id)
    {
        $container = Container::with([
            'orders.customer', 'orders.items', 'orders.lcs',
            'costs.category', 'costs.paymentAccount', 'costs.dollarEntries', 'documents',
        ])->findOrFail($id);

        return view('admin.backend.containers.show', [
            'container' => $container,
            'assignableOrders' => Order::with('customer', 'items')->latest()->get(),
            'costCategories' => CostCategory::where('is_active', true)->orderBy('name')->get(),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'documentTypes' => ContainerDocument::types(),
            'statuses' => Container::statuses(),
            'allocationBases' => Container::allocationBases(),
        ]);
    }

    public function edit($id)
    {
        $container = Container::findOrFail($id);

        return view('admin.backend.containers.edit', array_merge($this->formData(), compact('container')));
    }

    public function update(Request $request, $id)
    {
        Container::findOrFail($id)->update($this->validated($request));

        return redirect()->route('container.show', $id)->with('success', 'Container updated successfully.');
    }

    public function destroy($id)
    {
        $container = Container::findOrFail($id);
        $orders = $container->orders()->get();
        $container->delete();

        // Detached orders no longer carry this container's allocated cost.
        foreach ($orders as $order) {
            $order->recomputeFinancials();
        }

        return redirect()->route('container.index')->with('success', 'Container deleted successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $container = Container::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Container::statuses())),
        ]);

        $container->update($data);

        return redirect()->back()->with('success', 'Shipment status updated to '.$container->statusLabel().'.');
    }

    /**
     * Attach an order to the container (or update its loaded quantity). Re-allocates costs
     * across all member orders since the basis totals changed.
     */
    public function storeOrder(Request $request, $id)
    {
        $container = Container::findOrFail($id);

        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'ctn' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'cbm' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $pivot = [
            'ctn' => $data['ctn'] ?? 0,
            'weight' => $data['weight'] ?? 0,
            'cbm' => $data['cbm'] ?? 0,
            'remarks' => $data['remarks'] ?? null,
        ];

        $alreadyLoaded = $container->orders()->where('order_id', $data['order_id'])->exists();

        if ($alreadyLoaded) {
            $container->orders()->updateExistingPivot($data['order_id'], $pivot);
        } else {
            $container->orders()->attach($data['order_id'], $pivot);
        }

        // Membership lives in a pivot table, so it is logged by hand — on the order,
        // filed under the container so both histories show it.
        ActivityLog::record(
            Order::findOrFail($data['order_id']),
            ActivityLog::EDITED,
            ($alreadyLoaded ? 'Changed its load in container ' : 'Loaded into container ').$container->container_code,
            parent: $container,
        );

        $this->recomputeMembers($container);

        return redirect()->back()->with('success', 'Order assigned to container.');
    }

    public function destroyOrder($id, $orderId)
    {
        $container = Container::findOrFail($id);
        $container->orders()->detach($orderId);

        if ($order = Order::find($orderId)) {
            ActivityLog::record($order, ActivityLog::EDITED, 'Removed from container '.$container->container_code, parent: $container);
        }

        Order::find($orderId)?->recomputeFinancials();
        $this->recomputeMembers($container);

        return redirect()->back()->with('success', 'Order removed from container.');
    }

    /**
     * Record a container cost. When a payment account is chosen, it is paid out of that
     * account (debit into the ledger). The cost is then distributed across member orders.
     */
    public function storeCost(Request $request, $id)
    {
        $container = Container::findOrFail($id);

        $data = $request->validate(DollarLedger::withRules([
            'cost_category_id' => 'nullable|exists:cost_categories,id',
            'title' => 'required|string|max:255',
            'amount' => DollarLedger::amountRule(),
            'cost_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]));

        $data = DB::transaction(function () use ($container, $data, $request) {
            // Paid in dollars, the cost is what those dollars cost the account.
            $inDollars = DollarLedger::inDollars($data);
            if ($inDollars) {
                $data['amount'] = DollarLedger::costOf((int) $data['payment_account_id'], (float) $data['usd_amount']);
            }

            $attachment = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachment = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $dir = public_path('upload/containers');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file->move($dir, $attachment);
            }

            $cost = $container->costs()->create([
                'cost_category_id' => $data['cost_category_id'] ?? null,
                'title' => $data['title'],
                'amount' => $data['amount'],
                'cost_date' => $data['cost_date'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            if ($inDollars) {
                DollarLedger::spend((int) $data['payment_account_id'], (float) $data['usd_amount'], 'container_cost', [
                    'entry_date' => $cost->cost_date,
                    'description' => $data['title'].' for container '.$container->container_code,
                    'reference' => $container->container_number ?: $container->container_code,
                    'note' => $data['note'] ?? null,
                ], $cost);
            } elseif (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->decrement('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'debit',
                    'source' => 'container_cost',
                    'amount' => $data['amount'],
                    'credit' => 0,
                    'debit' => $data['amount'],
                    'running_balance' => $account->fresh()->balance,
                    'description' => $data['title'].' for container '.$container->container_code,
                    'reference' => $container->container_number ?: $container->container_code,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => ContainerCost::class,
                    'transactionable_id' => $cost->id,
                    'added_by' => Auth::id(),
                    'created_at' => $cost->cost_date,
                ]);
            }

            $this->recomputeMembers($container);

            return $data;
        });

        return redirect()->back()->with('success', 'Container cost of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    public function destroyCost($id, $costId)
    {
        $container = Container::findOrFail($id);
        $cost = $container->costs()->findOrFail($costId);

        DB::transaction(function () use ($container, $cost) {
            // Paid in dollars, they go back to the account; otherwise its taka does.
            if ($cost->payment_account_id && ! DollarLedger::reverseFor($cost)) {
                $account = PaymentAccount::find($cost->payment_account_id);
                if ($account) {
                    $account->increment('balance', (float) $cost->amount);
                }
                Transaction::where('transactionable_type', ContainerCost::class)
                    ->where('transactionable_id', $cost->id)
                    ->delete();
            }

            $cost->delete();
            $this->recomputeMembers($container);
        });

        return redirect()->back()->with('success', 'Container cost deleted.');
    }

    public function storeDocument(Request $request, $id)
    {
        $container = Container::findOrFail($id);

        $data = $request->validate([
            'type' => 'required|string|max:255',
            'provided_by' => 'nullable|string|max:255',
            'file' => 'required|file|mimes:pdf,csv,zip,doc,docx,xls,xlsx,jpeg,jpg,png|max:8192',
        ]);

        $file = $request->file('file');
        $name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/containers');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        $container->documents()->create([
            'type' => $data['type'],
            'provided_by' => $data['provided_by'] ?? null,
            'file' => $name,
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Document uploaded.');
    }

    public function destroyDocument($id, $docId)
    {
        $container = Container::findOrFail($id);
        $container->documents()->findOrFail($docId)->delete();

        return redirect()->back()->with('success', 'Document deleted.');
    }

    /**
     * Printable packing list — the container's orders and their items grouped by client.
     */
    public function packingList($id)
    {
        $container = Container::with(['orders.customer', 'orders.items'])->findOrFail($id);

        return view('admin.backend.containers.packing_list', compact('container'));
    }

    /**
     * Printable loading list — carton-level loading summary per order.
     */
    public function loadingList($id)
    {
        $container = Container::with(['orders.customer', 'orders.items'])->findOrFail($id);

        return view('admin.backend.containers.loading_list', compact('container'));
    }

    /**
     * Re-run the profit rollup for every order in the container (allocation changed).
     */
    private function recomputeMembers(Container $container): void
    {
        foreach ($container->orders()->get() as $order) {
            $order->recomputeFinancials();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'transportationModes' => TransportationMode::orderBy('name')->get(),
            'statuses' => Container::statuses(),
            'allocationBases' => Container::allocationBases(),
            'shipmentTypes' => Container::shipmentTypes(),
            'containerSizes' => Container::containerSizes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'shipment_no' => 'nullable|string|max:255',
            'shipment_type' => 'nullable|in:'.implode(',', array_keys(Container::shipmentTypes())),
            // FCL owns the box, so the container number is required; LCL rides on the
            // consolidator's container and is billed per CBM instead.
            'container_number' => 'required_if:shipment_type,fcl|nullable|string|max:255',
            'container_size' => 'nullable|in:'.implode(',', Container::containerSizes()),
            'lcl_rate' => 'nullable|numeric|min:0',
            'transport_mode' => 'nullable|string|max:255',
            'shipping_line' => 'nullable|string|max:255',
            'booking_ref' => 'nullable|string|max:255',
            'port_of_loading' => 'nullable|string|max:255',
            'port_of_discharge' => 'nullable|string|max:255',
            'etd' => 'nullable|date',
            'eta' => 'nullable|date',
            'status' => 'nullable|in:'.implode(',', array_keys(Container::statuses())),
            'allocation_basis' => 'nullable|in:'.implode(',', array_keys(Container::allocationBases())),
            'staffing_notes' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);
    }
}
