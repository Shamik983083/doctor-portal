<?php

namespace App\Console\Commands;

use App\Models\Clinician;
use Illuminate\Console\Command;

/**
 * Who loses their queue when blank licensure starts blocking.
 *
 * Devin msg 2313: "empty should not show licensed everywhere it needs to
 * reject". `Clinician::isLicensedInState()` is fail-closed as of 2026-07-23, so
 * any clinician with no recorded licensed states is now blocked from being
 * routed cases AND from prescribing or approving.
 *
 * RUN THIS BEFORE THE DEPLOY, NOT AFTER. It lists exactly who is affected and
 * exits non-zero while any remain, which makes it usable as a gate. Every name it
 * prints is a doctor whose queue goes quiet the moment this ships, and the fix
 * is thirty seconds of data entry per person on their admin screen.
 *
 * Exit codes: 0 = every active clinician has licensure recorded. 1 = at least
 * one does not.
 */
class LicensureAudit extends Command
{
    protected $signature = 'licensure:audit {--all : Include inactive clinicians}';

    protected $description = 'List clinicians with no recorded licensed states, who are now blocked from routing and prescribing';

    public function handle(): int
    {
        $query = Clinician::with('user');

        if (! $this->option('all')) {
            $query->where('status', 'active');
        }

        $clinicians = $query->get();

        $missing = $clinicians->filter(fn (Clinician $c) => empty($c->licensed_states));

        $this->info('Checked ' . $clinicians->count() . ' clinician(s).');

        if ($missing->isEmpty()) {
            $this->info('Every one has licensed states recorded. Blank-licensure blocking is safe to deploy.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error($missing->count() . ' clinician(s) have NO licensed states recorded.');
        $this->warn('Each of these is blocked from receiving cases, prescribing and approving until their licences are entered.');
        $this->newLine();

        $this->table(
            ['ID', 'Name', 'NPI', 'Status', 'Fix at'],
            $missing->map(fn (Clinician $c) => [
                $c->id,
                $c->user?->name ?? '(no user)',
                $c->npi,
                $c->status,
                '/admin/clinicians/' . $c->id . '/edit',
            ])->all(),
        );

        return self::FAILURE;
    }
}
