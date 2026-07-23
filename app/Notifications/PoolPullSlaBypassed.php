<?php

namespace App\Notifications;

use App\Models\CasePullRequest;
use App\Models\Clinician;
use App\Services\Routing\PoolEligibilityEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A doctor over their SLA pulled cases anyway, because their Doctor Admin set the
 * SLA to BYPASS (Devin msg 2313 Q6: "it should bypass, or seek approval").
 *
 * The work has already moved. This exists so "bypass" does not quietly mean
 * "nobody finds out": the admin who set the SLA is told each time it is crossed.
 */
class PoolPullSlaBypassed extends Notification
{
    use Queueable;

    public function __construct(
        private CasePullRequest $request,
        private Clinician $clinician,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $name = $this->clinician->user?->name ?? 'A doctor';

        return [
            'type'            => 'pool_pull_sla_bypassed',
            'title'           => 'Case request granted over SLA',
            'body'            => $name . ' pulled ' . $this->request->granted_count
                                  . ' cases from the pool while over SLA: ' . $this->reasonText()
                                  . '. Your policy allows the pull, so it went through.',
            'url'             => '/admin/clinicians/' . $this->clinician->id,
            'clinician_id'    => $this->clinician->id,
            'pull_request_id' => $this->request->id,
            'reasons'         => $this->request->blocking_reasons ?? [],
        ];
    }

    private function reasonText(): string
    {
        $labels = array_map(
            fn ($code) => PoolEligibilityEvaluator::REASON_LABELS[$code] ?? $code,
            $this->request->blocking_reasons ?? [],
        );

        return $labels === [] ? 'no reason recorded' : implode(', ', $labels);
    }
}
