<?php

namespace App\Console\Commands;

use App\Models\PatientCase;
use App\Services\TriageClassifier;
use Illuminate\Console\Command;

/**
 * Slice B — backfill triage bands onto existing cases.
 *
 *   php artisan cases:triage-backfill           # only cases with no triage yet
 *   php artisan cases:triage-backfill --all     # re-classify every case
 */
class TriageBackfill extends Command
{
    protected $signature = 'cases:triage-backfill {--all : Re-classify every case, not just unclassified ones}';

    protected $description = 'Classify cases into Green/Yellow/Red triage bands using the config/triage.php ruleset';

    public function handle(TriageClassifier $classifier): int
    {
        $query = PatientCase::query()
            ->with(['patient', 'caseOfferings.offering', 'caseQuestions', 'clinicalNotes'])
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('triage'));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No cases to classify.');
            return self::SUCCESS;
        }

        $this->info("Classifying {$total} case(s) with ruleset " . config('triage.version') . '…');
        $counts = [TriageClassifier::GREEN => 0, TriageClassifier::YELLOW => 0, TriageClassifier::RED => 0];

        $query->chunkById(200, function ($cases) use ($classifier, &$counts) {
            foreach ($cases as $case) {
                $result = $classifier->classify($case);
                $case->forceFill([
                    'triage'         => $result['level'],
                    'triage_reasons' => $result['reasons'],
                    'triage_ruleset' => $result['ruleset'],
                    'triaged_at'     => now(),
                ])->save();
                $counts[$result['level']]++;
            }
        });

        $this->table(
            ['Green', 'Yellow', 'Red'],
            [[$counts[TriageClassifier::GREEN], $counts[TriageClassifier::YELLOW], $counts[TriageClassifier::RED]]]
        );
        $this->info('Triage backfill complete.');

        return self::SUCCESS;
    }
}
