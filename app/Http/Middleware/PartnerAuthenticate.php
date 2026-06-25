<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;

class PartnerAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Attach partner via token's client_id
        $token = $user->token();

        if (!$token) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        $partner = Partner::where('oauth_client_id', $token->client_id)->first();

        if (!$partner || $partner->status !== 'active') {
            return response()->json(['message' => 'Partner account is inactive or not found.'], 403);
        }

        $request->merge(['_partner' => $partner]);
        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
