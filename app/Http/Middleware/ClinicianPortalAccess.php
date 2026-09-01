<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Every clinician-portal screen needs a clinician record behind the user.
 *
 * THE BUG THIS CLOSES. The clinician routes are gated `role:clinician|admin`,
 * so an admin can enter the portal, but an admin has no Clinician record.
 * Controllers throughout do:
 *
 *     $clinician = Auth::user()->clinician;   // null for an admin
 *     ...
 *     'clinician_id' => $clinician->id,       // fatal
 *
 * That is 11 call sites in Clinician\CaseController alone, plus the dashboard
 * and notification controllers. Guarding each one individually would fix
 * today's list and miss tomorrow's, because nothing stops the next handler
 * doing the same thing.
 *
 * So the check lives at the door instead, the same shape as
 * PartnerPortalAccess: if you are in the clinician portal, you have a clinician
 * record, and every controller downstream can rely on it.
 *
 * WHY REDIRECT RATHER THAN 403 FOR AN ADMIN. An admin reaching these URLs is
 * not an intrusion, it is a wrong turn (usually a link or a bookmark). Sending
 * them to their own dashboard with a plain explanation is the honest response.
 * Anyone else without a clinician record gets a 403, because for them it is not
 * a wrong turn.
 */
class ClinicianPortalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/login');
        }

        if ($user->clinician) {
            return $next($request);
        }

        // An admin or super admin with no clinician record: wrong turn, not intrusion.
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return redirect()->route('admin.dashboard')->with(
                'warning',
                'The clinician portal is for provider accounts. Your account is not linked to a '
                . 'clinician record, so there is no queue to show you.'
            );
        }

        abort(403, 'Your account is not linked to a clinician record.');
    }
}
