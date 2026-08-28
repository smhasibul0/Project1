<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    /**
     * The logged-in customer's linked contact id (every query is scoped to this).
     */
    private function contactId(): int
    {
        return (int) Auth::user()->contact_id;
    }

    public function dashboard()
    {
        $contactId = $this->contactId();

        $orders = Order::where('customer_id', $contactId)->get();

        return view('portal.dashboard', [
            'openOrders' => $orders->whereNotIn('goods_status', ['delivered', 'completed', 'cancelled'])->count(),
            'totalDue' => round($orders->sum(fn ($o) => (float) $o->due_amount), 2),
            'quotationCount' => Quotation::where('customer_id', $contactId)->count(),
            'recentOrders' => Order::where('customer_id', $contactId)->latest()->take(5)->get(),
        ]);
    }

    public function quotations()
    {
        $quotations = Quotation::where('customer_id', $this->contactId())
            ->withCount('items')->latest()->get();

        return view('portal.quotations.index', compact('quotations'));
    }

    public function quotationCreate()
    {
        return view('portal.quotations.create');
    }

    public function quotationStore(Request $request)
    {
        $data = $request->validate([
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.hs_code_id' => 'nullable|exists:hs_codes,id',
            'items.*.hs_code' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.package_quantity' => 'nullable|numeric|min:0',
            'items.*.net_weight' => 'nullable|numeric|min:0',
            'items.*.cbm' => 'nullable|numeric|min:0',
            'items.*.declared_value' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($data) {
            $quotation = Quotation::create([
                'customer_id' => $this->contactId(),
                'query_received_date' => now()->toDateString(),
                'status' => 'requested',
                'source' => 'customer',
                'submitted_to_customer' => false,
                'remarks' => $data['remarks'] ?? null,
                'added_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $row) {
                // Customers describe what they are shipping; we price it per CBM later.
                $quotation->items()->create([
                    'hs_code_id' => $row['hs_code_id'] ?? null,
                    'hs_code' => $row['hs_code'] ?? null,
                    'description' => $row['description'] ?? null,
                    'package_quantity' => $row['package_quantity'] ?? 0,
                    'net_weight' => $row['net_weight'] ?? null,
                    'cbm' => $row['cbm'] ?? null,
                    'declared_value' => $row['declared_value'] ?? 0,
                    'line_total' => 0,
                ]);
            }
        });

        return redirect()->route('portal.quotations')->with('success', 'Your request has been submitted. We will send you a quote soon.');
    }

    public function quotationShow($id)
    {
        $quotation = Quotation::with(['transportationMode', 'items.packingType'])
            ->where('customer_id', $this->contactId())
            ->findOrFail($id);

        return view('portal.quotations.show', compact('quotation'));
    }

    public function quotationAccept($id)
    {
        return $this->respondToQuote($id, 'accepted', 'Quotation accepted — we will process your order.');
    }

    public function quotationNegotiate($id)
    {
        return $this->respondToQuote($id, 'negotiating', 'We have received your negotiation request and will get back to you.');
    }

    /**
     * Respond to a quote the admin has sent (status "quoted"): accept or ask to negotiate.
     */
    private function respondToQuote($id, string $status, string $message)
    {
        $quotation = Quotation::where('customer_id', $this->contactId())->findOrFail($id);

        if ($quotation->status !== 'quoted') {
            return redirect()->back()->with('error', 'This quotation cannot be actioned yet.');
        }

        $quotation->update(['status' => $status]);

        return redirect()->route('portal.quotation.show', $quotation->id)->with('success', $message);
    }

    public function orders()
    {
        $orders = Order::where('customer_id', $this->contactId())
            ->withCount('items')->latest()->get();

        return view('portal.orders.index', compact('orders'));
    }

    public function orderShow($id)
    {
        $order = Order::with(['items', 'tracking.changedBy', 'payments'])
            ->where('customer_id', $this->contactId())
            ->findOrFail($id);

        return view('portal.orders.show', compact('order'));
    }

    public function payments()
    {
        $orders = Order::with('payments')
            ->where('customer_id', $this->contactId())
            ->latest()->get();

        return view('portal.payments', [
            'orders' => $orders,
            'totalDue' => round($orders->sum(fn ($o) => (float) $o->due_amount), 2),
            'totalPaid' => round($orders->sum(fn ($o) => (float) $o->received_amount), 2),
        ]);
    }
}
