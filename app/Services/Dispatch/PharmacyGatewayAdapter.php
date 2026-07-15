<?php

namespace App\Services\Dispatch;

/**
 * A pharmacy gateway adapter turns the structured order payload into an actual
 * POST /order to a pharmacy and returns a normalized result. Adapters MUST NOT
 * perform any real network call unless dispatch is both enabled and
 * sandbox-validated (see config/dispatch.php); the resolver enforces this.
 */
interface PharmacyGatewayAdapter
{
    /**
     * @param  array $payload  { order:{...}, rxs:[...], document:{ pdfBase64? } }
     * @return array           { ok:bool, code:int, body:string, reference:?string }
     */
    public function send(array $payload): array;

    public function key(): string;
}
