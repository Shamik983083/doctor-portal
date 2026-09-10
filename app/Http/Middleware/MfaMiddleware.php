<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MfaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('mfa_verified')) {
            // Preserve where they were going so verify() can redirect()->intended()
            return redirect()->route('mfa.verify');
        }

        return $next($request);
    }
}
