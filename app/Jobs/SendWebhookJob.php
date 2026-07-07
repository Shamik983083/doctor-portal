<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(private int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);

        if (!$delivery || $delivery->status === WebhookDelivery::STATUS_DELIVERED) {
            return;
        }

        $webhook = $delivery->webhook;

        if (!$webhook || $webhook->status !== 'active') {
            return;
        }

        $partner = $webhook->partner;

        $payload  = $delivery->payload;
        $payload['event'] = $delivery->event_type;
        $jsonBody = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonBody, $partner->webhook_secret ?? '');

        $delivery->increment('attempts');
        $delivery->update(['last_attempted_at' => now(), 'status' => WebhookDelivery::STATUS_RETRYING]);

        Log::info("Webhook dispatch", [
            'delivery_id' => $delivery->id,
            'event_type'  => $delivery->event_type,
            'url'         => $webhook->url,
            'attempt'     => $delivery->attempts,
            'payload'     => $payload,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-Webhook-Signature' => "sha256={$signature}",
                    'X-Event-Type'       => $delivery->event_type,
                ])
                ->post($webhook->url, $payload);

            if ($response->successful()) {
                $delivery->update([
                    'status'        => WebhookDelivery::STATUS_DELIVERED,
                    'response_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 1000),
                ]);

                Log::info("Webhook delivered", [
                    'delivery_id'   => $delivery->id,
                    'event_type'    => $delivery->event_type,
                    'url'           => $webhook->url,
                    'response_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 1000),
                ]);
                return;
            }

            Log::warning("Webhook failed", [
                'delivery_id'   => $delivery->id,
                'event_type'    => $delivery->event_type,
                'url'           => $webhook->url,
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
            ]);

            $this->handleFailure($delivery, $response->status(), $response->body());
        } catch (\Throwable $e) {
            Log::error("Webhook exception", [
                'delivery_id' => $delivery->id,
                'event_type'  => $delivery->event_type,
                'url'         => $webhook->url,
                'error'       => $e->getMessage(),
            ]);
            $this->handleFailure($delivery, 0, $e->getMessage());
        }
    }

    private function handleFailure(WebhookDelivery $delivery, int $code, string $body): void
    {
        if ($delivery->canRetry()) {
            $backoffSeconds = min(300, 30 * (2 ** ($delivery->attempts - 1)));
            $delivery->update([
                'status'        => WebhookDelivery::STATUS_RETRYING,
                'response_code' => $code,
                'response_body' => substr($body, 0, 1000),
                'next_retry_at' => now()->addSeconds($backoffSeconds),
            ]);
            self::dispatch($delivery->id)
                ->delay(now()->addSeconds($backoffSeconds))
                ->onQueue('webhooks');
        } else {
            $delivery->update([
                'status'        => WebhookDelivery::STATUS_FAILED,
                'response_code' => $code,
                'response_body' => substr($body, 0, 1000),
            ]);
        }
    }
}
