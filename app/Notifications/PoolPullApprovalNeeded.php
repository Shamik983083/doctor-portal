<?php

namespace App\Notifications;

use App\Models\CasePullRequest;
use App\Models\Clinician;
use App\Services\Routing\PoolEligibilityEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A doctor asked the pool for work while over their SLA, and their Doctor Admin
 * set that SLA to REQUIRE_APPROVAL (Devin msg 2313 Q6).
 *
 * Nothing has been granted. The request is sitting at PENDING_APPROVAL until an
 * admin approves or denies it, so this notification is the only thing that will
 * ever tell them it exists.
 */
class PoolPullApprovalNeeded extends Notification
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
            'type'            => 'pool_pull_approval_needed',
            'title'           => 'Case request waiting on you',
            'body'            => $name . ' asked for ' . $this->request->requested_count
                                  . ' cases from the pool and is over SLA: '
                                  . $this->reasonText() . '. Nothing has been granted yet.',
            'url'             => '/admin/routing/pull-requests',
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
