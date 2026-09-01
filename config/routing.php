<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routing exception escalation
    |--------------------------------------------------------------------------
    |
    | How long a case sits unroutable before admins are notified (Devin msg 2313
    | Q7: into the queue immediately, notify at a configurable age).
    |
    | The exception itself appears on /admin/routing/exceptions the moment
    | routing gives up. This only controls the escalation, so setting it low
    | makes the alerts noisy, not the record incomplete.
    |
    | Systemic failures (no active policy, malformed policy) ignore this entirely
    | and notify at once: every case in the system is affected.
    |
    */

    'exception_notify_after_hours' => env('ROUTING_EXCEPTION_NOTIFY_AFTER_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Pool pull defaults
    |--------------------------------------------------------------------------
    |
    | Fallback ceiling on a single pull request, used only when the active
    | routing policy sets no `maxCasesPerRequest`. The policy is the real home
    | for this, since it is versioned; this exists so an unconfigured policy
    | cannot let one request drain the queue.
    |
    */

    'pool_default_max_per_request' => env('ROUTING_POOL_MAX_PER_REQUEST', 50),

    /*
    |--------------------------------------------------------------------------
    | B3: Pull-assigned case completion deadline
    |--------------------------------------------------------------------------
    |
    | Hours from when a pool pull is granted until the clinician must complete
    | the case. If they miss the window, the case auto-releases back to WAITING
    | and a cooldown blocks them from pulling again for `deadline_cooldown_hours`.
    |
    */

    'completion_deadline_hours' => env('ROUTING_COMPLETION_DEADLINE_HOURS', 24),
    'deadline_cooldown_hours'   => env('ROUTING_DEADLINE_COOLDOWN_HOURS', 4),

];
