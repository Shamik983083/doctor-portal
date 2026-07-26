<?php

/**
 * SMS configuration.
 *
 * Mirrors the two-gate posture of config/ai.php deliberately:
 *
 * GATE 1 — SMS_ENABLED: master on/off switch. Off by default so the scaffold
 * can be deployed without activating anything.
 *
 * GATE 2 — SMS_BAA_CONFIRMED: even with the master on, a real (non-mock)
 * adapter refuses to send until an operator confirms the BAA is executed on
 * the Twilio account being used. SMS bodies may not carry PHI, but the
 * patient's phone number itself is PHI, so a signed BAA is required.
 *
 * SAFETY: with shipped defaults, MockSmsAdapter is active. All sends are
 * logged locally; no request leaves the system.
 */
return [
    // Master switch.
    'enabled' => env('SMS_ENABLED', false),

    // BAA confirmation gate. See config/ai.php for why this is separate.
    'baa_confirmed' => env('SMS_BAA_CONFIRMED', false),

    // Which driver to use when both gates are open: 'mock' (default) | 'twilio'
    'driver' => env('SMS_DRIVER', 'mock'),

    // Twilio credentials (only used when driver = 'twilio' and both gates open).
    'twilio' => [
        'sid'  => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM_NUMBER'),   // E.164 format: +15551234567
    ],

    // Debounce: minimum minutes between SMS sends on the same case.
    // One SMS per case per 30 minutes prevents chatty threads from spamming patients.
    'debounce_minutes' => (int) env('SMS_DEBOUNCE_MINUTES', 30),
];
