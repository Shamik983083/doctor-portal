<?php

namespace App\Services;

use App\Jobs\DispatchPharmacyOrderJob;
use App\Models\CaseEvent;
use App\Models\PharmacyDispatch;
use App\Models\PrescriptionDocument;
use App\Services\Dispatch\PharmacyGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a signed prescription document into a pharmacy `POST /order` and pushes
 * it through the outbox (MA-DOCPORTAL fundamentals port).
 *
 * The payload is built ONLY from the immutable document snapshot — structured
 * `rxs[]` plus, when attachment policy is on, the exact signed PDF as base64.
 *
 * SAFETY GATE: when `dispatch.enabled` is false (the default) we still record a
 * dispatch row so the operator can preview exactly what *would* be sent, but its
 * status is `disabled` and NO job is queued — nothing leaves the system. Only
 * when dispatch is enabled do we create the row as `pending` and hand it to the
 * retry/backoff/dead-letter job.
 */
class PharmacyDispatchService
{
    public function __construct(private PharmacyGatewayManager $gateway) {}

    public function queue(PrescriptionDocument $document): PharmacyDispatch
    {
        $existing = PharmacyDispatch::where('prescription_document_id', $document->id)->first();
        if ($existing) {
            return $existing;
        }

        $payload = $this->buildPayload($document);
        $enabled = $this->gateway->dispatchEnabled();

        $dispatch = DB::transaction(function () use ($document, $payload, $enabled) {
            $dispatch = PharmacyDispatch::create([
                'prescription_document_id' => $document->id,
                'case_id'                  => $document->case_id,
                'partner_id'               => $document->partner_id,
                'pharmacy_id'              => null,
                'adapter'                  => config('dispatch.adapter', 'mock'),
                'status'                   => $enabled
                    ? PharmacyDispatch::STATUS_PENDING
                    : PharmacyDispatch::STATUS_DISABLED,
                'attempts'                 => 0,
                'max_attempts'             => (int) config('dispatch.max_attempts', 5),
                'payload'                  => $payload,
            ]);

            CaseEvent::create([
                'case_id'    => $document->case_id,
                'event_type' => $enabled ? 'pharmacy.dispatch.queued' : 'pharmacy.dispatch.preview',
                'actor_type' => 'system',
                'actor_id'   => null,
                'payload'    => [
                    'dispatch_uuid'            => $dispatch->uuid,
                    'prescription_document_id' => $document->id,
                    'adapter'                  => $dispatch->adapter,
                    'enabled'                  => $enabled,
                    'rx_count'                 => count($payload['rxs'] ?? []),
                ],
                'notes'      => $enabled
                    ? 'Pharmacy dispatch queued for delivery.'
                    : 'Pharmacy dispatch recorded in preview (feature-flagged off) — nothing pushed.',
            ]);

            return $dispatch;
        });

        // Only hand off to the outbox worker when dispatch is actually enabled.
        if ($enabled) {
            DispatchPharmacyOrderJob::dispatch($dispatch->id);
        }

        return $dispatch;
    }

    /**
     * Build the MA-style order payload from the locked snapshot. Structured
     * `rxs[]` always; the signed PDF (base64) only when attachment policy is on.
     */
    public function buildPayload(PrescriptionDocument $document): array
    {
        $snapshot = $document->snapshot;

        $payload = [
            'order' => [
                'external_id'   => $snapshot['case']['external_id'] ?? null,
                'case_uuid'     => $snapshot['case']['uuid'] ?? null,
                'visit_type'    => $snapshot['case']['visit_type'] ?? null,
                'patient'       => $snapshot['patient'] ?? null,
                'prescriber'    => $snapshot['clinician'] ?? null,
                'diagnoses'     => $snapshot['diagnoses'] ?? null,
                'document_hash' => $document->content_hash,
            ],
            'rxs' => $snapshot['rxs'] ?? [],
        ];

        if (config('dispatch.attach_pdf', true)) {
            $disk = config('dispatch.documents_disk', 'local');
            if (Storage::disk($disk)->exists($document->document_path)) {
                $payload['document'] = [
                    'filename'  => basename($document->document_path),
                    'mime'      => 'application/pdf',
                    'sha256'    => $document->content_hash,
                    'pdfBase64' => base64_encode(Storage::disk($disk)->get($document->document_path)),
                ];
            }
        }

        return $payload;
    }
}
