<?php

namespace App\Jobs;

use App\Models\CaseEvent;
use App\Models\PharmacyDispatch;
use App\Services\Dispatch\PharmacyGatewayManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drains one pharmacy dispatch through the configured gateway adapter, with
 * retry/backoff/dead-letter (MA-DOCPORTAL outbox port). Modeled on SendWebhookJob.
 *
 * Each firing is a single attempt: it resolves the adapter (which itself enforces
 * the enabled + sandbox-validated gate), sends the payload, and records the
 * result. On failure it either schedules the next retry (exponential backoff) or
 * marks the row dead-letter once max_attempts is exhausted. Every terminal
 * outcome writes an immutable CaseEvent.
 */
class DispatchPharmacyOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(private int $dispatchId) {}

    public function handle(PharmacyGatewayManager $gateway): void
    {
        $dispatch = PharmacyDispatch::find($this->dispatchId);

        if (! $dispatch) {
            return;
        }

        // Terminal or preview states are never (re)sent.
        if (in_array($dispatch->status, [
            PharmacyDispatch::STATUS_SENT,
            PharmacyDispatch::STATUS_DEAD_LETTER,
            PharmacyDispatch::STATUS_DISABLED,
        ], true)) {
            return;
        }

        $dispatch->update([
            'status'            => PharmacyDispatch::STATUS_SENDING,
            'attempts'          => $dispatch->attempts + 1,
            'last_attempted_at' => now(),
        ]);

        try {
            $adapter = $gateway->resolve();
            $result  = $adapter->send($dispatch->payload ?? []);

            if (! empty($result['ok'])) {
                $dispatch->update([
                    'status'        => PharmacyDispatch::STATUS_SENT,
                    'response_code' => $result['code'] ?? null,
                    'response_body' => $this->truncate($result['body'] ?? null),
                    'external_ref'  => $result['reference'] ?? null,
                    'dispatched_at' => now(),
                    'next_retry_at' => null,
                ]);

                $this->audit($dispatch, 'pharmacy.dispatch.sent', 'system', [
                    'external_ref' => $result['reference'] ?? null,
                    'response_code'=> $result['code'] ?? null,
                    'attempts'     => $dispatch->attempts,
                ], 'Pharmacy accepted the order.');

                return;
            }

            $this->fail($dispatch, (string) ($result['body'] ?? 'gateway rejected'), $result['code'] ?? null);
        } catch (Throwable $e) {
            Log::warning('Pharmacy dispatch attempt failed', [
                'dispatch_id' => $dispatch->id,
                'attempt'     => $dispatch->attempts,
                'error'       => $e->getMessage(),
            ]);
            $this->fail($dispatch, $e->getMessage(), null);
        }
    }

    /** Record a failed attempt: schedule a retry, or dead-letter if exhausted. */
    private function fail(PharmacyDispatch $dispatch, string $reason, ?int $code): void
    {
        if ($dispatch->attempts >= $dispatch->max_attempts) {
            $dispatch->update([
                'status'        => PharmacyDispatch::STATUS_DEAD_LETTER,
                'response_code' => $code,
                'response_body' => $this->truncate($reason),
                'next_retry_at' => null,
            ]);

            $this->audit($dispatch, 'pharmacy.dispatch.dead_letter', 'system', [
                'attempts' => $dispatch->attempts,
                'reason'   => $this->truncate($reason, 500),
            ], 'Pharmacy dispatch exhausted all attempts — moved to dead-letter for manual review.');

            return;
        }

        // Exponential backoff: 1m, 2m, 4m, 8m, ... capped at 1h.
        $delaySeconds = min(3600, 60 * (2 ** ($dispatch->attempts - 1)));
        $retryAt      = now()->addSeconds($delaySeconds);

        $dispatch->update([
            'status'        => PharmacyDispatch::STATUS_FAILED,
            'response_code' => $code,
            'response_body' => $this->truncate($reason),
            'next_retry_at' => $retryAt,
        ]);

        $this->audit($dispatch, 'pharmacy.dispatch.retry_scheduled', 'system', [
            'attempts'      => $dispatch->attempts,
            'next_retry_at' => $retryAt->toIso8601String(),
        ], "Pharmacy dispatch attempt {$dispatch->attempts} failed — retry scheduled.");

        self::dispatch($dispatch->id)->delay($retryAt);
    }

    private function audit(PharmacyDispatch $dispatch, string $type, string $actorType, array $payload, string $notes): void
    {
        CaseEvent::create([
            'case_id'    => $dispatch->case_id,
            'event_type' => $type,
            'actor_type' => $actorType,
            'actor_id'   => null,
            'payload'    => array_merge(['dispatch_uuid' => $dispatch->uuid], $payload),
            'notes'      => $notes,
        ]);
    }

    private function truncate(?string $value, int $max = 2000): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }
}
