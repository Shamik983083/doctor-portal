<?php

namespace App\Services\Dispatch;

use RuntimeException;

/**
 * DISABLED real-pharmacy adapter stub (LifeFile-style direct dispatch).
 *
 * Every real-effect path throws until the operator has cleared the sandbox and
 * enabled dispatch — mirrors MA's LifeFile-disabled-until-sandbox-approval
 * design. When implemented, `send()` would build the LifeFile `POST /order`
 * (structured rxs[] + `order.document.pdfBase64` when attachment policy is on)
 * against sandbox credentials from the secret store, verify the response, and
 * normalize it. It intentionally does nothing today.
 */
class LifeFilePharmacyAdapter implements PharmacyGatewayAdapter
{
    public function key(): string { return 'lifefile'; }

    public function send(array $payload): array
    {
        throw new RuntimeException(
            'LifeFile direct dispatch is not enabled. It stays disabled until sandbox validation '
            . 'is complete and PHARMACY_DISPATCH_SANDBOX_VALIDATED=true. Use the mock adapter for staging.'
        );
    }
}
