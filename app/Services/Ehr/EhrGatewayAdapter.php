<?php

namespace App\Services\Ehr;

/**
 * An EHR gateway adapter turns the structured record payload into an actual
 * create-record call against an EHR and returns a normalized result. Adapters
 * MUST NOT perform any real network call unless EHR push is both enabled and
 * sandbox-validated (see config/ehr.php); the resolver enforces this.
 */
interface EhrGatewayAdapter
{
    /**
     * @param  array $payload  { patient:{...}, encounter:{...}, note:{...}, medications:[...] }
     * @return array           { ok:bool, code:int, body:string, reference:?string }
     */
    public function createRecord(array $payload): array;

    public function key(): string;
}
