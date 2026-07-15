<?php

/**
 * Prescription dispatch configuration (MA-DOCPORTAL fundamentals port).
 *
 * Mirrors MA's posture: a provider-approved order becomes ONE immutable signed
 * PDF from a locked snapshot, and dispatch to a pharmacy gateway is
 * **feature-flagged and mutually gated** — it never fires against a real
 * pharmacy unless BOTH `enabled` and `sandbox_validated` are true, and the
 * default adapter is a no-network mock. This is the same discipline as MA's
 * VRIO-mock / LifeFile-disabled-until-sandbox-approval design.
 *
 * SAFETY: with the shipped defaults, prescribing generates the signed document
 * and records a dispatch row in status `disabled` (preview) — nothing is pushed
 * anywhere. Turning on real dispatch is a deliberate, two-flag operator action.
 */
return [
    // Master switch. Off by default.
    'enabled' => env('PHARMACY_DISPATCH_ENABLED', false),

    // Second gate. Even with `enabled=true`, a real (non-mock) adapter refuses
    // to send until the operator has validated the vendor sandbox and sets this.
    'sandbox_validated' => env('PHARMACY_DISPATCH_SANDBOX_VALIDATED', false),

    // Which gateway to use: 'mock' (default, no network) | 'lifefile' (disabled stub).
    'adapter' => env('PHARMACY_DISPATCH_ADAPTER', 'mock'),

    // Attachment policy: include the signed PDF (base64) in the order payload.
    'attach_pdf' => env('PHARMACY_DISPATCH_ATTACH_PDF', true),

    // Outbox retry policy for the dispatch job.
    'max_attempts' => (int) env('PHARMACY_DISPATCH_MAX_ATTEMPTS', 5),

    // Storage disk + folder for the immutable signed prescription PDFs.
    'documents_disk' => env('PHARMACY_DOCUMENTS_DISK', 'local'),
    'documents_dir'  => 'prescription-documents',

    // Standard provider attestation embedded in the locked snapshot + PDF.
    // (In MA this is captured with step-up auth per patient/order — see the
    // architecture note; here it is a fixed clinical attestation string.)
    'attestation' => 'I certify that I have reviewed this patient\'s intake and clinical information, '
        . 'that a valid provider-patient relationship exists, and that this prescription is medically '
        . 'appropriate and issued in the usual course of professional practice.',
];
