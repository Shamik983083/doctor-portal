<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function token(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'grant_type'    => 'required|in:client_credentials',
        ]);

        // Proxy to Passport's token endpoint
        $response = Http::asForm()->post(url('/oauth/token'), [
            'grant_type'    => 'client_credentials',
            'client_id'     => $request->client_id,
            'client_secret' => $request->client_secret,
            'scope'         => '',
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $partner = Partner::where('client_id', $request->client_id)->first();

        return response()->json(array_merge(
            $response->json(),
            ['partner_id' => $partner?->uuid]
        ));
    }
}
