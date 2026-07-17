{{--
    Immutable signed prescription document (MA-DOCPORTAL fundamentals port).
    Rendered ONCE from the locked provider-approved order snapshot by
    PrescriptionDocumentService. The PDF bytes are content-hashed; do not make
    this template depend on anything outside $snapshot, or reprints will not
    reproduce the same hash.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a2233; font-size: 12px; margin: 0; }
        .sheet { padding: 36px 40px; }
        .masthead { border-bottom: 3px solid #2d4a7a; padding-bottom: 10px; margin-bottom: 18px; }
        .masthead h1 { font-size: 18px; color: #2d4a7a; margin: 0 0 2px; }
        .masthead .sub { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .rx-symbol { float: right; font-size: 34px; color: #2d4a7a; font-weight: bold; line-height: 1; }
        .grid { width: 100%; margin-bottom: 16px; }
        .grid td { vertical-align: top; padding: 3px 8px 3px 0; width: 50%; }
        .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .value { font-size: 12px; color: #1a2233; margin-bottom: 6px; }
        .section-title { font-size: 11px; color: #2d4a7a; text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 1px solid #d6dde8; padding-bottom: 4px; margin: 18px 0 8px; font-weight: bold; }
        table.meds { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.meds th { background: #eef2f8; text-align: left; padding: 6px 8px; font-size: 9px;
            text-transform: uppercase; letter-spacing: 0.5px; color: #40536e; border: 1px solid #d6dde8; }
        table.meds td { padding: 6px 8px; font-size: 11px; border: 1px solid #d6dde8; vertical-align: top; }
        .attestation { margin-top: 20px; background: #f6f8fb; border: 1px solid #d6dde8; border-radius: 4px;
            padding: 12px 14px; font-size: 10.5px; color: #40536e; font-style: italic; }
        .sig { margin-top: 26px; }
        .sig .line { border-top: 1px solid #1a2233; width: 260px; padding-top: 4px; }
        .footer { margin-top: 28px; border-top: 1px solid #d6dde8; padding-top: 8px;
            font-size: 8.5px; color: #9aa5b5; }
        .hash { font-family: DejaVu Sans Mono, monospace; word-break: break-all; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="masthead">
        <span class="rx-symbol">&#8478;</span>
        <h1>Prescription</h1>
        <div class="sub">Provider-Approved &middot; Signed &amp; Locked</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Patient</div>
                <div class="value">{{ $snapshot['patient']['name'] ?? 'N/A' }}</div>
            </td>
            <td>
                <div class="label">Date of Birth</div>
                <div class="value">{{ $snapshot['patient']['date_of_birth'] ?? 'N/A' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Prescriber</div>
                <div class="value">{{ $snapshot['clinician']['name'] ?? 'N/A' }}</div>
            </td>
            <td>
                <div class="label">NPI</div>
                <div class="value">{{ $snapshot['clinician']['npi'] ?? 'N/A' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Patient State</div>
                <div class="value">{{ $snapshot['patient']['state'] ?? ($snapshot['case']['state'] ?? 'N/A') }}</div>
            </td>
            <td>
                <div class="label">Case</div>
                <div class="value">{{ $snapshot['case']['external_id'] ?? $snapshot['case']['uuid'] ?? 'N/A' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Diagnoses</div>
    <div class="value">{{ $snapshot['diagnoses'] ?? 'Not specified' }}</div>

    <div class="section-title">Medications</div>
    <table class="meds">
        <thead>
            <tr>
                <th>Medication</th>
                <th>Qty</th>
                <th>Refills</th>
                <th>Days Supply</th>
                <th>Directions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($snapshot['rxs'] ?? [] as $rx)
                <tr>
                    <td>
                        <strong>{{ $rx['name'] }}</strong>
                        @if(!empty($rx['compound_formula']))
                            <br><span style="color:#6b7280;">{{ $rx['compound_formula'] }}</span>
                        @endif
                    </td>
                    <td>{{ $rx['quantity'] ?? '—' }} {{ $rx['dispense_unit'] ?? '' }}</td>
                    <td>{{ $rx['refills'] ?? '0' }}</td>
                    <td>{{ $rx['days_supply'] ?? '—' }}</td>
                    <td>{{ $rx['directions'] ?? $snapshot['directions'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#9aa5b5;">No medications on this order.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($snapshot['medical_necessity']))
        <div class="section-title">Medical Necessity</div>
        <div class="value">{{ $snapshot['medical_necessity'] }}</div>
    @endif

    <div class="attestation">{{ $snapshot['attestation'] ?? '' }}</div>

    <div class="sig">
        <div class="line">
            {{ $snapshot['clinician']['name'] ?? '' }}
            @if(!empty($snapshot['clinician']['npi'])) &middot; NPI {{ $snapshot['clinician']['npi'] }} @endif
        </div>
        <div class="label" style="margin-top:4px;">
            Electronically signed {{ $snapshot['prescribed_at'] ?? $snapshot['generated_at'] ?? '' }}
        </div>
    </div>

    <div class="footer">
        This document was generated from a locked, provider-approved order snapshot and is an immutable
        clinical record. Snapshot schema v{{ $snapshot['schema_version'] ?? 1 }} &middot;
        Generated {{ $snapshot['generated_at'] ?? '' }}.
    </div>
</div>
</body>
</html>
