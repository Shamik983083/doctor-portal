<?php

namespace App\Services\Ehr;

use Illuminate\Support\Str;

/**
 * No-network mock EHR gateway (the safe default). It validates the payload shape
 * and returns a synthetic "created" response with a fake record id. It never
 * contacts any real system.
 *
 * Mirrors MockPharmacyAdapter so the whole approval-to-record pipeline can be
 * exercised in staging with no vendor account and no real effect.
 */
class MockEhrAdapter implements EhrGatewayAdapter
{
    public function key(): string { return 'mock'; }

    public function createRecord(array $payload): array
    {
        // Minimal shape validation. A chart record with no note and no
        // medications is not a record worth creating, and silently accepting
        // one would hide a builder bug until it reached the real vendor.
        $hasNote = trim((string) ($payload['note']['text'] ?? '')) !== '';
        $hasMeds = ! empty($payload['medications'] ?? []);

        if (! $hasNote && ! $hasMeds) {
            return [
                'ok'        => false,
                'code'      => 422,
                'body'      => 'payload carries neither a note nor any medications',
                'reference' => null,
            ];
        }

        if (empty($payload['patient']['external_id'] ?? null)) {
            return [
                'ok'        => false,
                'code'      => 422,
                'body'      => 'payload has no patient identifier',
                'reference' => null,
            ];
        }

        return [
            'ok'        => true,
            'code'      => 201,
            'body'      => json_encode([
                'created'   => true,
                'has_note'  => $hasNote,
                'med_count' => count($payload['medications'] ?? []),
            ]),
            'reference' => 'MOCK-EHR-' . strtoupper(Str::random(10)),
        ];
    }
}
