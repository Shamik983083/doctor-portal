<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private WebhookDispatcher $webhookDispatcher) {}

    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    public function index(Request $request)
    {
        $webhooks = $this->partner($request)->webhooks()
            ->when($request->event_type, fn($q, $e) => $q->where('event_type', $e))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->paginate(25);

        return response()->json($webhooks);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url'        => 'required|url',
            'event_type' => 'nullable|string',
            'status'     => 'nullable|in:active,inactive',
        ]);

        $webhook = $this->partner($request)->webhooks()->create($data);

        return response()->json($webhook, 201);
    }

    public function show(Request $request, string $id)
    {
        $webhook = $this->partner($request)->webhooks()->where('uuid', $id)->firstOrFail();

        return response()->json($webhook->load('deliveries'));
    }

    public function update(Request $request, string $id)
    {
        $webhook = $this->partner($request)->webhooks()->where('uuid', $id)->firstOrFail();

        $data = $request->validate([
            'url'        => 'sometimes|url',
            'event_type' => 'nullable|string',
            'status'     => 'nullable|in:active,inactive',
        ]);

        $webhook->update($data);

        return response()->json($webhook);
    }

    public function destroy(Request $request, string $id)
    {
        $webhook = $this->partner($request)->webhooks()->where('uuid', $id)->firstOrFail();
        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted.']);
    }

    public function resend(Request $request, string $deliveryId)
    {
        $delivery = WebhookDelivery::whereHas('webhook', fn($q) => $q->where('partner_id', $this->partner($request)->id))
            ->where('uuid', $deliveryId)
            ->firstOrFail();

        $this->webhookDispatcher->resend($delivery);

        return response()->json(['message' => 'Webhook queued for resend.']);
    }
}
