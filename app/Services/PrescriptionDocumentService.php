<?php

namespace App\Services;

use App\Models\CaseEvent;
use App\Models\CasePrescription;
use App\Models\PatientCase;
use App\Models\PrescriptionDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the immutable signed prescription document from a locked,
 * provider-approved order snapshot (MA-DOCPORTAL fundamentals port).
 *
 * On provider approval we:
 *   1. Freeze a snapshot of the exact order the provider signed off on
 *      (patient identity, clinician + NPI, diagnoses, directions, medications,
 *      and the attestation).
 *   2. Render ONE PDF from that snapshot via dompdf.
 *   3. Hash the PDF bytes (sha256) so any later tampering is detectable.
 *   4. Persist an append-only PrescriptionDocument row + write a
 *      `prescription.document.generated` audit event (immutable CaseEvent).
 *
 * The snapshot — not the live records — is the source of truth for anything
 * downstream (the pharmacy dispatch payload, reprints), so post-hoc edits to the
 * case can never change what was dispatched.
 */
class PrescriptionDocumentService
{
    /**
     * Generate the signed document for a just-approved prescription.
     * Idempotent per prescription: returns the existing document if one exists.
     */
    public function generate(PatientCase $case, CasePrescription $prescription): PrescriptionDocument
    {
        $existing = PrescriptionDocument::where('case_prescription_id', $prescription->id)->first();
        if ($existing) {
            return $existing;
        }

        $prescription->loadMissing('medications', 'clinician.user');
        $case->loadMissing('patient', 'partner');

        $snapshot = $this->buildSnapshot($case, $prescription);

        $pdf   = Pdf::loadView('pdf.prescription', ['snapshot' => $snapshot]);
        $bytes = $pdf->output();
        $hash  = hash('sha256', $bytes);

        $disk = config('dispatch.documents_disk', 'local');
        $dir  = trim(config('dispatch.documents_dir', 'prescription-documents'), '/');
        $path = "{$dir}/{$case->uuid}/rx-{$prescription->id}-{$hash}.pdf";

        Storage::disk($disk)->put($path, $bytes);

        return DB::transaction(function () use ($case, $prescription, $snapshot, $path, $hash) {
            $document = PrescriptionDocument::create([
                'case_prescription_id' => $prescription->id,
                'case_id'              => $case->id,
                'patient_id'           => $case->patient_id,
                'partner_id'           => $case->partner_id,
                'clinician_id'         => $prescription->clinician_id,
                'snapshot'             => $snapshot,
                'document_path'        => $path,
                'content_hash'         => $hash,
                'attestation'          => $snapshot['attestation'],
                'attested_at'          => now(),
                'locked_at'            => now(),
            ]);

            CaseEvent::create([
                'case_id'    => $case->id,
                'event_type' => 'prescription.document.generated',
                'actor_type' => 'clinician',
                'actor_id'   => $prescription->clinician_id,
                'payload'    => [
                    'prescription_document_id' => $document->id,
                    'uuid'                     => $document->uuid,
                    'content_hash'             => $hash,
                    'rx_count'                 => count($snapshot['rxs']),
                ],
                'notes'      => 'Immutable signed prescription document generated from locked order snapshot.',
            ]);

            return $document;
        });
    }

    /**
     * Freeze the provider-approved order into a self-contained snapshot. This is
     * the ONLY thing the PDF and the pharmacy payload are built from.
     */
    private function buildSnapshot(PatientCase $case, CasePrescription $prescription): array
    {
        $patient   = $case->patient;
        $clinician = $prescription->clinician;

        $rxs = $prescription->medications->map(fn ($m) => [
            'name'                => $m->name,
            'compound_formula'    => $m->compound_formula,
            'refills'             => $m->refills !== null ? (int) $m->refills : null,
            'quantity'            => $m->quantity !== null ? (string) $m->quantity : null,
            'days_supply'         => $m->days_supply !== null ? (int) $m->days_supply : null,
            'dispense_unit'       => $m->dispense_unit,
            'days_until_dispense' => $m->days_until_dispense !== null ? (int) $m->days_until_dispense : null,
            'directions'          => $prescription->directions,
        ])->values()->all();

        return [
            'schema_version' => 1,
            'generated_at'   => now()->toIso8601String(),
            'case' => [
                'uuid'        => $case->uuid,
                'external_id' => $case->external_id,
                'visit_type'  => $case->visit_type,
                'state'       => $case->patient_state ?? ($patient->state ?? null),
            ],
            'patient' => [
                'uuid'          => $patient?->uuid,
                'name'          => $patient ? trim("{$patient->first_name} {$patient->last_name}") : null,
                'date_of_birth' => $patient?->date_of_birth
                    ? $patient->date_of_birth->format('Y-m-d')
                    : null,
                'state'         => $patient?->state,
            ],
            'clinician' => [
                'name'    => $clinician?->full_name,
                'npi'     => $clinician?->npi,
                'license' => $clinician?->license_number ?? null,
            ],
            'partner' => [
                'id'   => $case->partner_id,
                'name' => $case->partner?->name,
            ],
            'diagnoses'         => $prescription->diagnoses,
            'directions'        => $prescription->directions,
            'medical_necessity' => $prescription->medical_necessity,
            'rxs'               => $rxs,
            'prescribed_at'     => optional($prescription->prescribed_at)->toIso8601String(),
            'attestation'       => config('dispatch.attestation'),
        ];
    }

    /** Verify a stored PDF still matches its recorded content hash. */
    public function verifyIntegrity(PrescriptionDocument $document): bool
    {
        $disk = config('dispatch.documents_disk', 'local');
        if (! Storage::disk($disk)->exists($document->document_path)) {
            return false;
        }
        return hash('sha256', Storage::disk($disk)->get($document->document_path)) === $document->content_hash;
    }
}
