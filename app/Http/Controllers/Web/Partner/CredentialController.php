<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CredentialController extends Controller
{
    public function show()
    {
        $partner = Auth::user()->partner;
        $partner->makeVisible('client_secret');

        $webhooks = $partner->webhooks()->latest()->get();

        return view('partner.credentials', compact('partner', 'webhooks'));
    }
}
