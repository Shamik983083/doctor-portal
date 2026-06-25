<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Partner;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    public function index(Request $request)
    {
        $orders = $this->partner($request)->orders()
            ->with(['patient', 'case', 'pharmacy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function show(Request $request, string $id)
    {
        $order = $this->partner($request)->orders()
            ->with(['patient', 'case', 'pharmacy', 'prescriptions'])
            ->where('uuid', $id)->firstOrFail();

        return response()->json($order);
    }

    public function update(Request $request, string $id)
    {
        $order = $this->partner($request)->orders()->where('uuid', $id)->firstOrFail();

        $data = $request->validate([
            'status'           => 'sometimes|string',
            'tracking_number'  => 'nullable|string',
            'tracking_carrier' => 'nullable|string',
            'payment_status'   => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $old = $order->status;
        $order->update($data);

        if (isset($data['status']) && $data['status'] !== $old) {
            $this->webhooks->dispatch($order->partner_id, 'order_status_changed', [
                'order_id'  => $order->uuid,
                'case_id'   => $order->case->uuid ?? null,
                'status'    => $order->status,
                'timestamp' => now()->timestamp,
            ]);
        }

        if (isset($data['tracking_number'])) {
            $this->webhooks->dispatch($order->partner_id, 'tracking_number_changed', [
                'order_id'        => $order->uuid,
                'tracking_number' => $order->tracking_number,
                'timestamp'       => now()->timestamp,
            ]);
        }

        return response()->json($order);
    }

    public function cancel(Request $request, string $id)
    {
        $order = $this->partner($request)->orders()->where('uuid', $id)->firstOrFail();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        $this->webhooks->dispatch($order->partner_id, 'order_status_changed', [
            'order_id'  => $order->uuid,
            'status'    => 'cancelled',
            'timestamp' => now()->timestamp,
        ]);

        return response()->json(['message' => 'Order cancelled.', 'order' => $order]);
    }
}
