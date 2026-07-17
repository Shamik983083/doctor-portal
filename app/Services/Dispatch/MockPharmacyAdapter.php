<?php

namespace App\Services\Dispatch;

use Illuminate\Support\Str;

/**
 * No-network mock pharmacy gateway (the safe default). It validates the payload
 * shape and returns a synthetic "accepted" response with a fake pharmacy order
 * id. It never contacts any real system — mirrors MA's MockVrioAdapter, used for
 * staging/sandbox exercise of the full dispatch pipeline without real effects.
 */
class MockPharmacyAdapter implements PharmacyGatewayAdapter
{
    public function key(): string { return 'mock'; }

    public function send(array $payload): array
    {
        // Minimal shape validation — the pipeline should never send an empty order.
        $rxs = $payload['rxs'] ?? [];
        if (empty($rxs)) {
            return ['ok' => false, 'code' => 422, 'body' => 'rxs[] is empty', 'reference' => null];
        }

        return [
            'ok'        => true,
            'code'      => 201,
            'body'      => json_encode(['accepted' => true, 'rx_count' => count($rxs)]),
            'reference' => 'MOCK-' . strtoupper(Str::random(10)),
        ];
    }
}
