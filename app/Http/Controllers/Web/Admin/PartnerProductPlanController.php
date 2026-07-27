<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\Partner;
use App\Models\PartnerProductPlan;
use Illuminate\Http\Request;

class PartnerProductPlanController extends Controller
{
    private const ALLOWED_FREQUENCIES = [1, 3, 6, 12];

    public function index(int $partnerId)
    {
        $partner = Partner::findOrFail($partnerId);

        $plans = PartnerProductPlan::where('partner_id', $partner->id)
            ->with('offering')
            ->orderBy('product_key')
            ->orderBy('month_frequency')
            ->get();

        // Group by product_key for easier display
        $grouped = $plans->groupBy('product_key');

        // Offerings this partner can access (for the create form dropdown)
        $accessibleOfferings = $partner->accessibleOfferings()
            ->where('offerings.is_active', true)
            ->orderBy('offerings.name')
            ->get(['offerings.id', 'offerings.name', 'offerings.internal_name']);

        return view('admin.partners.product-plans', compact('partner', 'grouped', 'accessibleOfferings'));
    }

    public function store(Request $request, int $partnerId)
    {
        $partner = Partner::findOrFail($partnerId);

        $data = $request->validate([
            'product_key'      => 'required|string|max:120',
            'month_frequency'  => 'required|integer|in:' . implode(',', self::ALLOWED_FREQUENCIES),
            'offering_id'      => 'required|integer|exists:offerings,id',
            'label'            => 'nullable|string|max:120',
        ]);

        // Ensure the offering is actually accessible by this partner
        $offering = $partner->accessibleOfferings()
            ->where('offerings.id', $data['offering_id'])
            ->first();

        if (!$offering) {
            return back()->withErrors(['offering_id' => 'That offering is not accessible for this partner.'])->withInput();
        }

        // Check for duplicate (partner + product_key + month_frequency must be unique)
        $exists = PartnerProductPlan::where('partner_id', $partner->id)
            ->where('product_key', $data['product_key'])
            ->where('month_frequency', $data['month_frequency'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'product_key' => "A plan for product_key \"{$data['product_key']}\" with month_frequency {$data['month_frequency']} already exists.",
            ])->withInput();
        }

        PartnerProductPlan::create([
            'partner_id'      => $partner->id,
            'product_key'     => $data['product_key'],
            'offering_id'     => $data['offering_id'],
            'month_frequency' => $data['month_frequency'],
            'label'           => $data['label'] ?? null,
        ]);

        return back()->with('success', "Plan \"{$data['product_key']}\" ({$data['month_frequency']}M) created.");
    }

    public function destroy(int $partnerId, int $planId)
    {
        $partner = Partner::findOrFail($partnerId);
        $plan    = PartnerProductPlan::where('partner_id', $partner->id)->findOrFail($planId);

        $label = "\"{$plan->product_key}\" ({$plan->month_frequency}M)";
        $plan->delete();

        return back()->with('success', "Plan {$label} deleted. Existing cases are unaffected — they retain their product_key snapshot.");
    }
}
