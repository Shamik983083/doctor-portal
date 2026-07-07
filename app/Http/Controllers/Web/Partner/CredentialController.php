<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CredentialController extends Controller
{
    private const EVENT_TYPES = [
        'case_waiting', 'case_assigned_to_clinician', 'case_support',
        'case_approved', 'case_processing', 'case_completed', 'case_cancelled',
        'prescription_written', 'message_created',
    ];

    public function show()
    {
        $partner = Auth::user()->partner;
        $partner->makeVisible('client_secret');

        $webhooks = $partner->webhooks()->latest()->get();

        return view('partner.credentials', compact('partner', 'webhooks'));
    }

    public function storeWebhook(Request $request)
    {
        $partner = Auth::user()->partner;

        $data = $request->validate([
            'url'        => ['required', 'url'],
            'event_type' => ['nullable', Rule::in(self::EVENT_TYPES)],
        ]);

        $partner->webhooks()->create([
            'url'        => $data['url'],
            'event_type' => $data['event_type'] ?? null,
            'status'     => 'active',
        ]);

        return redirect()->route('partner.credentials')->with('success', 'Webhook added.');
    }

    public function updateWebhook(int $id)
    {
        $partner = Auth::user()->partner;
        $webhook = $partner->webhooks()->findOrFail($id);

        $webhook->update([
            'status' => $webhook->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->route('partner.credentials')->with('success', 'Webhook status updated.');
    }

    public function destroyWebhook(int $id)
    {
        $partner = Auth::user()->partner;
        $webhook = $partner->webhooks()->findOrFail($id);

        $webhook->delete();

        return redirect()->route('partner.credentials')->with('success', 'Webhook deleted.');
    }
}
