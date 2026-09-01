<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $slaSettings              = Setting::group('sla');
        $medicalNecessityPreset   = Setting::get('medical_necessity_preset', '');
        $messageRoutingMode       = Setting::get('message_routing_mode', 'direct');
        return view('admin.settings', compact('slaSettings', 'medicalNecessityPreset', 'messageRoutingMode'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'sla_pickup_hours'         => 'required|integer|min:1|max:168',
            'sla_review_hours'         => 'required|integer|min:1|max:168',
            'sla_total_hours'          => 'required|integer|min:1|max:720',
            'medical_necessity_preset' => 'nullable|string|max:2000',
            'message_routing_mode'     => 'required|in:direct,pool',
        ]);

        Setting::set('sla_pickup_hours',         $request->integer('sla_pickup_hours'));
        Setting::set('sla_review_hours',         $request->integer('sla_review_hours'));
        Setting::set('sla_total_hours',          $request->integer('sla_total_hours'));
        Setting::set('medical_necessity_preset', $request->input('medical_necessity_preset', ''));
        Setting::set('message_routing_mode',     $request->input('message_routing_mode', 'direct'));

        return back()->with('success', 'Settings updated successfully.');
    }
}
