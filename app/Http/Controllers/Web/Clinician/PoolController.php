<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Http\Controllers\Controller;
use App\Models\CasePullRequest;
use App\Models\RoutingPolicy;
use App\Services\Routing\PoolEligibilityEvaluator;
use App\Services\Routing\PoolPullService;
use Illuminate\Http\Request;

/**
 * A doctor asking the pool for cases (Devin msg 2308).
 *
 * "A PROVIDER CAN REQUEST CASES (A NUMBER, THE DON'T SEE WHAT'S AVAILABLE)."
 *
 * THE QUEUE IS NEVER RENDERED HERE, and that is the whole design of the screen,
 * not an omission. A doctor who can see the queue can cherry-pick it, and the
 * oldest-first guarantee that keeps the tail of the queue from rotting only
 * holds if nobody gets to choose. They see a number box and their own history.
 */
class PoolController extends Controller
{
    public function index(PoolEligibilityEvaluator $eligibility)
    {
        $clinician = auth()->user()->clinician;

        abort_unless($clinician, 403);

        $policy = RoutingPolicy::active();

        return view('clinician.pool.index', [
            'clinician'   => $clinician,
            // Shown so a doctor who is blocked learns it before typing a number,
            // rather than by submitting and being refused.
            'eligibility' => $eligibility->evaluate($clinician, $policy),
            'maxPerRequest' => $eligibility->maxPerRequest($policy)
                ?? config('routing.pool_default_max_per_request', 50),
            'remainingToday' => $eligibility->remainingToday($clinician, $policy),
            'requests'    => CasePullRequest::where('clinician_id', $clinician->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'reasonLabels' => PoolEligibilityEvaluator::REASON_LABELS,
        ]);
    }

    public function store(Request $request, PoolPullService $pool, PoolEligibilityEvaluator $eligibility)
    {
        $clinician = auth()->user()->clinician;

        abort_unless($clinician, 403);

        $ceiling = $eligibility->maxPerRequest()
            ?? (int) config('routing.pool_default_max_per_request', 50);

        $data = $request->validate([
            'count' => 'required|integer|min:1|max:' . $ceiling,
        ]);

        $pullRequest = $pool->request($clinician, (int) $data['count']);

        /*
         * Every outcome gets a plain answer, because the doctor cannot see the
         * queue and has no other way to find out what happened. "Nothing was
         * transferred" with no reason is the exact failure mode this feature was
         * asked to remove.
         */
        return back()->with(match ($pullRequest->status) {
            CasePullRequest::STATUS_GRANTED => $pullRequest->granted_count > 0 ? 'success' : 'warning',
            CasePullRequest::STATUS_PENDING_APPROVAL => 'warning',
            default => 'error',
        }, $this->outcomeMessage($pullRequest));
    }

    private function outcomeMessage(CasePullRequest $request): string
    {
        return match ($request->status) {
            CasePullRequest::STATUS_PENDING_APPROVAL =>
                'Your request is with your Doctor Admin: you are over an SLA they set. Nothing has been transferred yet.',

            CasePullRequest::STATUS_REJECTED =>
                'No cases transferred. ' . $this->reasonText($request),

            CasePullRequest::STATUS_GRANTED => $request->granted_count === 0
                ? 'No cases matched. ' . ($request->shortfall_reason ?? 'Nothing in the queue fits your states, categories or visit types right now.')
                : $request->granted_count . ' case(s) added to your queue.'
                    . ($request->wasShort() ? ' ' . $request->shortfall_reason : ''),

            default => 'Request recorded.',
        };
    }

    private function reasonText(CasePullRequest $request): string
    {
        $labels = array_map(
            fn ($code) => PoolEligibilityEvaluator::REASON_LABELS[$code] ?? $code,
            $request->blocking_reasons ?? [],
        );

        return $labels === [] ? 'No reason was recorded.' : implode('. ', $labels) . '.';
    }
}
