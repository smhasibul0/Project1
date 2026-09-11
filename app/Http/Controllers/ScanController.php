<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Order;
use App\Models\OrderScan;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScanController extends Controller
{
    /** How long a decoded carton stays open for recording. */
    private const KEY_TTL_SECONDS = 900;

    /**
     * The carton scanner: a camera pointed at the QR on a package.
     *
     * Recording only happens through here. Reading the carton with the camera is
     * what proves somebody is standing in front of the goods, so there is no
     * form to fill in from a desk.
     */
    public function index()
    {
        return view('scan');
    }

    /**
     * Details for the carton just decoded, plus the key that lets it be recorded.
     */
    public function lookup(Request $request, string $token): JsonResponse
    {
        $order = Order::with(['items', 'scans', 'customer', 'containers'])
            ->where('track_token', $token)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'That code does not belong to any order.'], 404);
        }

        $key = Str::random(40);
        $request->session()->put($this->sessionKey($token), ['key' => $key, 'at' => now()->timestamp]);

        $totalCartons = $order->totalCartons();

        return response()->json([
            'scan_key' => $key,
            'record_url' => route('order.scan', $token),
            'order_no' => $order->order_no,
            'shipping_mark' => $order->shipping_mark,
            'customer' => $order->customer->business_name ?: $order->customer->name ?? '',
            'status' => $order->statusLabel(),
            'container' => $order->containerNumbers(),
            'total_cartons' => $totalCartons,
            'stages' => collect(OrderScan::STAGES)->map(fn (array $stage, string $key) => [
                'key' => $key,
                'action' => $stage['action'],
                'label' => $stage['label'],
                'counted' => $order->cartonsScannedAt($key),
            ])->values(),
            'containers' => Container::whereIn('status', ['booked', 'in_transit'])->latest('id')->get()
                ->map(fn (Container $container) => [
                    'id' => $container->id,
                    'label' => trim($container->container_code.' · '.($container->container_number ?: 'no number')),
                ]),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get()
                ->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'label' => $warehouse->name]),
        ]);
    }

    /**
     * Check the key handed out when this carton was decoded, and burn it — one
     * recorded scan per read of the code, so a second batch means a second scan.
     */
    public static function consumeKey(Request $request, string $token, ?string $key): bool
    {
        $stored = $request->session()->get(self::sessionKeyFor($token));

        if (! $key || ! $stored || ! hash_equals($stored['key'], $key)) {
            return false;
        }

        $request->session()->forget(self::sessionKeyFor($token));

        return (now()->timestamp - $stored['at']) <= self::KEY_TTL_SECONDS;
    }

    private function sessionKey(string $token): string
    {
        return self::sessionKeyFor($token);
    }

    private static function sessionKeyFor(string $token): string
    {
        return 'carton_scan.'.$token;
    }
}
