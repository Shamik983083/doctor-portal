<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerPortalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!$user->partner_id || !$user->partner) {
            abort(403, 'Your account is not linked to a partner organisation.');
        }

        if ($user->partner->status !== 'active') {
            abort(403, 'Your partner account is suspended or inactive.');
        }

        return $next($request);
    }
}
