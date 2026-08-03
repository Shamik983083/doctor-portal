<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Ensures every application setting key exists in the database with
 * correct metadata and a sensible default value.
 *
 * IDEMPOTENT STRATEGY
 * -------------------
 * Value    — written only on CREATE. If an admin has already changed a
 *             setting through the UI, their value is preserved.
 * Metadata — (label, group, type, description) are always updated so a
 *             re-run can correct a label without resetting the admin's value.
 *
 * WHY NOT RELY ON FALLBACKS IN Setting::get()
 * -------------------------------------------
 * Setting::get() falls back to a hardcoded default when the row does not
 * exist, which works for reads. But admin screens that call Setting::group()
 * to list all settings in a group will show nothing for keys that have never
 * been written. This seeder ensures every key is a real row from day one.
 *
 * Depends on: nothing (no other seeders required first).
 */
class SettingsSeeder extends Seeder
{
    /**
     * [key, default_value, label, group, type, description]
     *
     * type is one of: text | number | boolean
     */
    private const SETTINGS = [
        // ── SLA ──────────────────────────────────────────────────────────────
        [
            'key'         => 'sla_pickup_hours',
            'default'     => '4',
            'label'       => 'Queue Pickup Deadline',
            'group'       => 'sla',
            'type'        => 'number',
            'description' => 'Maximum hours for a case to move from waiting to assigned.',
        ],
        [
            'key'         => 'sla_review_hours',
            'default'     => '24',
            'label'       => 'Review & Approval Deadline',
            'group'       => 'sla',
            'type'        => 'number',
            'description' => 'Maximum hours for a clinician to approve or decline after assignment.',
        ],
        [
            'key'         => 'sla_total_hours',
            'default'     => '48',
            'label'       => 'End-to-End Case Deadline',
            'group'       => 'sla',
            'type'        => 'number',
            'description' => 'Maximum hours from case creation to completion.',
        ],

        // ── General ──────────────────────────────────────────────────────────
        [
            'key'         => 'medical_necessity_preset',
            'default'     => '',
            'label'       => 'Medical Necessity Preset',
            'group'       => 'general',
            'type'        => 'text',
            'description' => 'Default medical necessity text pre-filled on the prescribe screen for the clinician to edit.',
        ],
        [
            'key'         => 'message_routing_mode',
            'default'     => 'direct',
            'label'       => 'Message Routing Mode',
            'group'       => 'general',
            'type'        => 'text',
            'description' => 'How inbound patient messages are routed: "direct" sends to the assigned clinician; "pool" routes to any available clinician.',
        ],

        // ── AI integrations ───────────────────────────────────────────────────
        // These are the DB-layer toggles controlled by the AI Settings screen
        // (SA5-b). Env vars are the source of truth for API keys and adapter
        // type; these rows only track enabled/baa_confirmed so an admin can
        // toggle without a deploy. Default is off — no PHI leaves the system
        // until both are explicitly confirmed.
        [
            'key'         => 'ai.integrations.clinical.enabled',
            'default'     => '0',
            'label'       => 'Clinical AI — Enabled',
            'group'       => 'ai',
            'type'        => 'boolean',
            'description' => 'Master switch for the clinical AI integration (clinical notes, messages, case summaries).',
        ],
        [
            'key'         => 'ai.integrations.clinical.baa_confirmed',
            'default'     => '0',
            'label'       => 'Clinical AI — BAA Confirmed',
            'group'       => 'ai',
            'type'        => 'boolean',
            'description' => 'Attests that a signed BAA covering the clinical AI integration is in place. Required before any PHI is transmitted.',
        ],
        [
            'key'         => 'ai.integrations.operations.enabled',
            'default'     => '0',
            'label'       => 'Operations AI — Enabled',
            'group'       => 'ai',
            'type'        => 'boolean',
            'description' => 'Master switch for the operations AI integration (Karen intake confirmation messages).',
        ],
        [
            'key'         => 'ai.integrations.operations.baa_confirmed',
            'default'     => '0',
            'label'       => 'Operations AI — BAA Confirmed',
            'group'       => 'ai',
            'type'        => 'boolean',
            'description' => 'Attests that a signed BAA covering the operations AI integration is in place.',
        ],
    ];

    public function run(): void
    {
        $created = 0;
        $updated = 0;

        foreach (self::SETTINGS as $cfg) {
            $existing = Setting::where('key', $cfg['key'])->first();

            if (! $existing) {
                Setting::create([
                    'key'         => $cfg['key'],
                    'value'       => $cfg['default'],
                    'label'       => $cfg['label'],
                    'group'       => $cfg['group'],
                    'type'        => $cfg['type'],
                    'description' => $cfg['description'],
                ]);
                $created++;
            } else {
                // Preserve the admin's value. Only update display metadata.
                $existing->update([
                    'label'       => $cfg['label'],
                    'group'       => $cfg['group'],
                    'type'        => $cfg['type'],
                    'description' => $cfg['description'],
                ]);
                $updated++;
            }
        }

        $this->command->info("SettingsSeeder: {$created} created, {$updated} metadata-updated.");
    }
}
