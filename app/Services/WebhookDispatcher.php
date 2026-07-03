<?php

namespace App\Services;

use App\Jobs\SendWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

class WebhookDispatcher
{
    public function dispatch(int $partnerId, string $eventType, array $payload): void
    {
        $webhooks = Webhook::where('partner_id', $partnerId)
            ->where('status', 'active')
            ->where(function ($q) use ($eventType) {
                $q->whereNull('event_type')->orWhere('event_type', $eventType);
            })
            ->get();

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::create([
                'webhook_id'  => $webhook->id,
                'event_type'  => $eventType,
                'payload'     => $payload,
                'status'      => WebhookDelivery::STATUS_PENDING,
                'max_attempts' => 5,
            ]);

            SendWebhookJob::dispatch($delivery->id)->onQueue('webhooks')->afterCommit();
        }
    }

    public function resend(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status'        => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        SendWebhookJob::dispatch($delivery->id)->onQueue('webhooks');
    }

    public function buildSignedPayload(Webhook $webhook, array $payload): array
    {
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, $webhook->secret);

        return [
            'payload'   => $payload,
            'signature' => $signature,
        ];
    }
}
