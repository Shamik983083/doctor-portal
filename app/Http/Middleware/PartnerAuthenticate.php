<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\AuthenticationException;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class PartnerAuthenticate
{
    public function __construct(protected ResourceServer $server)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        // Validate the Bearer token and extract the client_id.
        // Client credentials grants have no user, so we resolve
        // the partner directly from the OAuth client_id.
        $psrRequest = (new PsrHttpFactory)->createRequest($request);

        try {
            $psrRequest = $this->server->validateAuthenticatedRequest($psrRequest);
        } catch (OAuthServerException) {
            throw new AuthenticationException;
        }

        $clientId = $psrRequest->getAttribute('oauth_client_id');

        $partner = Partner::where('oauth_client_id', $clientId)->first();

        if (!$partner || $partner->status !== 'active') {
            return response()->json(['message' => 'Partner account is inactive or not found.'], 403);
        }

        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
