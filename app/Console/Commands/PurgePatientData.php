<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes all patients, cases, and related records EXCEPT those
 * belonging to a specified email address.
 *
 * Usage:
 *   php artisan patients:purge --except=persaud.devin@gmail.com
 *   php artisan patients:purge --except=persaud.devin@gmail.com --dry-run
 */
class PurgePatientData extends Command
{
    protected $signature = 'patients:purge
                            {--except= : Email address of the patient to preserve (required)}
                            {--dry-run : Show what would be deleted without deleting anything}';

    protected $description = 'Delete all patients and cases except those belonging to a given email';

    public function handle(): int
    {
        $email = $this->option('except');

        if (! $email) {
            $this->error('--except email is required. Example: php artisan patients:purge --except=someone@example.com');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        // ── Find what to keep ───────────────────────────────────────────────
        $keepPatientIds = DB::table('patients')
            ->where('email', $email)
            ->pluck('id')
            ->all();

        if (empty($keepPatientIds)) {
            $this->error("No patient found with email [{$email}]. Aborting — nothing deleted.");
            return self::FAILURE;
        }

        $keepCaseIds = DB::table('cases')
            ->whereIn('patient_id', $keepPatientIds)
            ->pluck('id')
            ->all();

        $this->info("Preserving patient ID(s): " . implode(', ', $keepPatientIds));
        $this->info("Preserving case ID(s):    " . (empty($keepCaseIds) ? '(none)' : implode(', ', $keepCaseIds)));
        $this->newLine();

        // ── Count what will be removed ──────────────────────────────────────
        $totalPatients = DB::table('patients')->whereNotIn('id', $keepPatientIds)->count();
        $totalCases    = DB::table('cases')->whereNotIn('patient_id', $keepPatientIds)->count();

        $this->line("Patients to delete: {$totalPatients}");
        $this->line("Cases to delete:    {$totalCases}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — nothing was deleted. Remove --dry-run to execute.');
            return self::SUCCESS;
        }

        if (! $this->confirm("This will permanently delete {$totalPatients} patient(s) and {$totalCases} case(s) from the LIVE database. Are you sure?")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        // ── Execute deletions in FK-safe order ──────────────────────────────
        $this->info('Starting deletion…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Case-level child tables (keyed on case_id)
        $caseTables = [
            'notifications',            // case_uuid based — handled via patients below
            'messages',
            'pharmacy_dispatches',
            'prescription_documents',
            'case_prescription_medications',
            'case_prescription_diagnoses_codes',
            'case_prescriptions',
            'ehr_records',
            'case_questions',
            'questionnaire_answers',
            'questionnaire_responses',
            'case_events',
            'case_tags',
            'case_diseases',
            'case_offerings',
            'clinical_notes',
            'orders',
            'patient_files',
            'routing_exceptions',
            'pool_requests',
            'clinician_supervisor_assignments', // if referenced by case indirectly
        ];

        // Tables with a direct case_id FK
        $caseIdTables = [
            'messages',
            'pharmacy_dispatches',
            'prescription_documents',
            'case_prescription_medications',
            'case_prescription_diagnoses_codes',
            'case_prescriptions',
            'ehr_records',
            'case_questions',
            'questionnaire_answers',
            'questionnaire_responses',
            'case_events',
            'case_tags',
            'case_diseases',
            'case_offerings',
            'clinical_notes',
            'orders',
            'patient_files',
            'routing_exceptions',
            'pool_requests',
        ];

        $deleteCaseIds = DB::table('cases')
            ->whereNotIn('patient_id', $keepPatientIds)
            ->pluck('id')
            ->all();

        if (! empty($deleteCaseIds)) {
            foreach ($caseIdTables as $table) {
                $n = DB::table($table)->whereIn('case_id', $deleteCaseIds)->delete();
                $this->line("  deleted {$n} rows from {$table}");
            }

            // Notifications are stored with a JSON data payload; delete by case uuid
            $deleteCaseUuids = DB::table('cases')
                ->whereIn('id', $deleteCaseIds)
                ->pluck('uuid')
                ->all();

            if (! empty($deleteCaseUuids)) {
                $n = DB::table('notifications')
                    ->whereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.case_uuid'))"), $deleteCaseUuids)
                    ->delete();
                $this->line("  deleted {$n} rows from notifications");
            }
        }

        // Delete cases
        $n = DB::table('cases')->whereNotIn('patient_id', $keepPatientIds)->delete();
        $this->line("  deleted {$n} rows from cases");

        // Delete patients
        $n = DB::table('patients')->whereNotIn('id', $keepPatientIds)->delete();
        $this->line("  deleted {$n} rows from patients");

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Done. Patient ' . $email . ' and their cases are untouched.');

        return self::SUCCESS;
    }
}
