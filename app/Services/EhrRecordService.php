<?php

namespace App\Services;

use App\Models\CaseEvent;
use App\Models\ClinicalNote;
use App\Models\EhrRecord;
use App\Models\PatientCase;
use App\Services\Ehr\EhrGatewayManager;
use Illuminate\Support\Facades\DB;

/**
 * Turns an approved case plus its signed clinical note into an EHR create-record
 * call and records it in the outbox.
 *
 * SAFETY GATE, identical in posture to PharmacyDispatchService: when
 * `ehr.enabled` is false (the default) we still build the payload and store the
 * row so an operator can preview exactly what WOULD be sent, but its status is
 * `disabled` and nothing leaves the system. Only when EHR push is enabled AND
 * sandbox-validated is the row created as `pending` and actually sent.
 *
 * THE NOTE IS THE PROVIDER'S, NOT THE MODEL'S. This service reads a persisted
 * ClinicalNote, which by then has been through the provider's hands. Nothing
 * here calls the AI, and an AI draft that a provider never accepted can never
 * reach an EHR by this path.
 */
class EhrRecordService
{
    public function __construct(private EhrGatewayManager $gateway) {}

    /**
     * Build (and, when enabled, push) the EHR record for an approved case.
     *
     * Idempotent by case + note: approving twice must not create two charts.
     */
    public function recordApproval(PatientCase $case, ?ClinicalNote $note, array $decisions = []): EhrRecord
    {
        $existing = EhrRecord::where('case_id', $case->id)
            ->where('clinical_note_id', $note?->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $payload = $this->buildPayload($case, $note, $decisions);

        // Company-scoped: a storefront that is not fully configured previews
        // rather than erroring on every approval.
        $enabled = $this->gateway->pushEnabled($case->partner_id);

        $record = DB::transaction(function () use ($case, $note, $payload, $enabled) {
            $record = EhrRecord::create([
                'case_id'          => $case->id,
                'clinical_note_id' => $note?->id,
                'partner_id'       => $case->partner_id,
                'adapter'          => config('ehr.adapter', 'mock'),
                'status'           => $enabled ? EhrRecord::STATUS_PENDING : EhrRecord::STATUS_DISABLED,
                'attempts'         => 0,
                'payload'          => $payload,
            ]);

            // The audit trail should show that a record was built even when the
            // integration is off, otherwise "nothing happened" and "it was
            // deliberately not sent" look identical later.
            CaseEvent::create([
                'case_id'    => $case->id,
                'event_type' => 'ehr_record_built',
                'actor_type' => 'system',
                'actor_id'   => null,
                'payload'    => [
                    'ehr_record_uuid' => $record->uuid,
                    'adapter'         => config('ehr.adapter', 'mock'),
                    'partner_id'      => $case->partner_id,
                ],
                'notes'      => $enabled
                    ? 'EHR record queued for ' . config('ehr.adapter') . '.'
                    : 'EHR record built and stored as a preview. Push is disabled, nothing was sent.',
            ]);

            return $record;
        });

        if (! $enabled) {
            return $record;
        }

        return $this->push($record);
    }

    /**
     * Send a pending record through the resolved adapter.
     *
     * Failure is recorded on the row, never thrown at the caller: an EHR being
     * down must not roll back a clinical decision the provider has already made.
     */
    public function push(EhrRecord $record): EhrRecord
    {
        try {
            // Resolved for THIS record's company, so the credential used is
            // always the one belonging to the storefront that owns the case.
            $partnerId = $record->payload['company']['partner_id'] ?? $record->partner_id;
            $result = $this->gateway->resolve($partnerId)->createRecord($record->payload);
        } catch (\Throwable $e) {
            $record->update([
                'status'     => EhrRecord::STATUS_FAILED,
                'attempts'   => $record->attempts + 1,
                'last_error' => $e->getMessage(),
            ]);

            return $record;
        }

        $record->update([
            'status'        => ! empty($result['ok']) ? EhrRecord::STATUS_SENT : EhrRecord::STATUS_FAILED,
            'attempts'      => $record->attempts + 1,
            'reference'     => $result['reference'] ?? null,
            'response_code' => $result['code'] ?? null,
            'response_body' => $result['body'] ?? null,
            'last_error'    => ! empty($result['ok']) ? null : ($result['body'] ?? 'unknown error'),
            'sent_at'       => ! empty($result['ok']) ? now() : null,
        ]);

        return $record;
    }

    /**
     * The record payload.
     *
     * Built from the case, the patient and the provider's own note. Explicit
     * field by field for the same reason the AI context is: a whitelist means
     * adding a column later cannot quietly start sending something new to a
     * third party.
     *
     * This shape is vendor-neutral on purpose. Mapping it onto Healthie's
     * GraphQL objects is the adapter's job, and is the piece still open (see
     * HealthieEhrAdapter for exactly what has to be decided first).
     */
    public function buildPayload(PatientCase $case, ?ClinicalNote $note, array $decisions = []): array
    {
        $patient = $case->patient;

        /*
         * TENANT SEGREGATION. Healthie is independent of this system and its data
         * MUST NOT be crossed between storefronts. If the same person comes in
         * through storefront A and later through storefront B, those are two
         * separate records and nothing from the first may be reused for the second.
         *
         * This app already gets that right upstream: intake creates patients with
         * firstOrCreate(['email' => ..., 'partner_id' => ...]), so the same human
         * from two storefronts is already two Patient rows. The risk is entirely
         * at THIS boundary, where a careless identity key would collapse them back
         * into one chart on the vendor side.
         *
         * Two guards, both deliberate:
         *  1. A partner is REQUIRED. No company id, no payload, because a record
         *     that cannot be attributed to a storefront is the one most likely to
         *     be merged into another.
         *  2. The case's partner and the patient's partner must MATCH. A mismatch
         *     is definitionally a cross-tenant leak and must fail loudly here
         *     rather than quietly write into the wrong company's chart.
         */
        if (empty($case->partner_id)) {
            throw new \RuntimeException(
                "Refusing to build an EHR payload for case {$case->uuid}: it has no partner. Healthie records "
                . 'must tie to a company id and an unattributed record risks being merged across storefronts.'
            );
        }

        if ((int) $patient->partner_id !== (int) $case->partner_id) {
            throw new \RuntimeException(
                "Refusing to build an EHR payload for case {$case->uuid}: the case belongs to partner "
                . "{$case->partner_id} but the patient belongs to partner {$patient->partner_id}. That mismatch "
                . 'would cross one storefront\'s data into another.'
            );
        }

        return [
            'source' => [
                'system'  => 'MEDAXIS',
                'case_id' => $case->uuid,
                'external_id' => $case->external_id,
                'built_at' => now()->toIso8601String(),
            ],

            /*
             * The company this record belongs to. Top level and mandatory, because
             * every downstream decision the adapter makes (which Healthie
             * organisation, which patient to match, whether to create) has to be
             * scoped by it.
             */
            'company' => [
                'partner_id'   => $case->partner_id,
                'partner_uuid' => $case->partner?->uuid,
                'name'         => $case->partner?->name,
                'slug'         => $case->partner?->slug,
            ],

            'patient' => [
                /*
                 * NAMESPACED ON PURPOSE. Never a bare external_id: that value is
                 * supplied by the storefront, is nullable, and two storefronts can
                 * legitimately issue the same one. Prefixing with the partner makes
                 * a collision across companies impossible by construction, so the
                 * vendor cannot match storefront B's patient onto storefront A's
                 * chart even if every other field looks identical.
                 */
                'external_id'   => $this->scopedPatientKey($case, $patient),
                'source_external_id' => $patient->external_id,
                'first_name'    => $patient->first_name,
                'last_name'     => $patient->last_name,
                'email'         => $patient->email,
                'phone'         => $patient->phone,
                'date_of_birth' => optional($patient->date_of_birth)->toDateString()
                    ?? $patient->date_of_birth,
                'gender'        => $patient->gender,
                'state'         => $patient->state,
            ],

            'encounter' => [
                'visit_type'  => $case->visit_type,
                'status'      => $case->status,
                'approved_at' => optional($case->approved_at)->toIso8601String(),
                'triage'      => $case->triage,
                'clinician'   => [
                    'name' => $case->clinician?->full_name,
                    'npi'  => $case->clinician?->npi,
                ],
            ],

            'note' => [
                'type'       => $note?->type,
                'text'       => (string) ($note?->note ?? ''),
                'written_by' => $case->clinician?->full_name,
                'written_at' => optional($note?->created_at)->toIso8601String(),
            ],

            'medications' => array_values(array_map(fn ($d) => [
                'name'      => $d['name'] ?? null,
                'decision'  => $d['decision'] ?? null,
                'term'      => $d['term'] ?? null,
                'frequency' => $d['frequency'] ?? null,
                'months'    => array_values(array_filter($d['months'] ?? [])),
                'refills'   => $d['refills'] ?? null,
            ], array_filter($decisions, fn ($d) => ($d['decision'] ?? null) === 'approve'))),

            // Declined medications travel too. A chart that shows only what was
            // approved loses the fact that something was actively considered and
            // turned down, which is clinically meaningful.
            'declined' => array_values(array_map(fn ($d) => [
                'name' => $d['name'] ?? null,
            ], array_filter($decisions, fn ($d) => ($d['decision'] ?? null) === 'deny'))),
        ];
    }

    /**
     * The identity this patient is known by on the vendor side, namespaced to the
     * storefront that owns them.
     *
     * Uses the partner uuid rather than the numeric id so the key stays stable and
     * meaningful if these ever move between environments, and falls back to the
     * patient uuid when the storefront supplied no external id of its own. Both
     * halves are always present, so the key can never collapse to a value another
     * storefront could also produce.
     */
    public function scopedPatientKey(PatientCase $case, $patient): string
    {
        $tenant = $case->partner?->uuid ?: ('partner-' . $case->partner_id);
        $local  = $patient->external_id ?: $patient->uuid;

        return $tenant . ':' . $local;
    }
}
