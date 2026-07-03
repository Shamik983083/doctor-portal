<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\ServerRequest as Psr7Request;

class AuthController extends Controller
{
    public function token(Request $request, AccessTokenController $tokenController)
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'grant_type'    => 'required|in:client_credentials',
        ]);

        // Call Passport's token issuer directly (avoids HTTP self-request deadlock)
        $psr7Request = (new Psr7Request('POST', url('/oauth/token')))
            ->withParsedBody([
                'grant_type'    => 'client_credentials',
                'client_id'     => $request->client_id,
                'client_secret' => $request->client_secret,
                'scope'         => '',
            ]);

        $response = $tokenController->issueToken($psr7Request, new Psr7Response());
        $statusCode = $response->getStatusCode();
        $data = json_decode($response->getContent(), true);

        if ($statusCode !== 200) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $partner = Partner::where('client_id', $request->client_id)->first();

        return response()->json(array_merge(
            $data,
            ['partner_id' => $partner?->uuid]
        ));
    }
}
