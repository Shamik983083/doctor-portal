<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class WebhookDeliveryController extends Controller
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function index(Request $request)
    {
        $deliveries = WebhookDelivery::with('webhook.partner')
            ->when($request->partner_id, fn($q, $id) => $q->whereHas('webhook', fn($q) => $q->where('partner_id', $id)))
            ->when($request->status,     fn($q, $s)  => $q->where('status', $s))
            ->when($request->event_type, fn($q, $e)  => $q->where('event_type', $e))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $partners = Partner::orderBy('name')->get(['id', 'name']);

        return view('admin.webhooks.index', compact('deliveries', 'partners'));
    }

    public function resend(string $uuid)
    {
        $delivery = WebhookDelivery::where('uuid', $uuid)->firstOrFail();

        $this->dispatcher->resend($delivery);

        return back()->with('success', 'Webhook delivery re-queued.');
    }
}
