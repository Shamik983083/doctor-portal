<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $slaSettings = Setting::group('sla');
        return view('admin.settings', compact('slaSettings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'sla_pickup_hours' => 'required|integer|min:1|max:168',
            'sla_review_hours' => 'required|integer|min:1|max:168',
            'sla_total_hours'  => 'required|integer|min:1|max:720',
        ]);

        Setting::set('sla_pickup_hours', $request->integer('sla_pickup_hours'));
        Setting::set('sla_review_hours', $request->integer('sla_review_hours'));
        Setting::set('sla_total_hours',  $request->integer('sla_total_hours'));

        return back()->with('success', 'SLA settings updated successfully.');
    }
}
