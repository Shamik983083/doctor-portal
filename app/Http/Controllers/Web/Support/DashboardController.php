<?php

namespace App\Http\Controllers\Web\Support;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;

class DashboardController extends Controller
{
    public function index()
    {
        $supportCount = PatientCase::where('status', PatientCase::STATUS_SUPPORT)->count();

        return view('support.dashboard', compact('supportCount'));
    }
}
