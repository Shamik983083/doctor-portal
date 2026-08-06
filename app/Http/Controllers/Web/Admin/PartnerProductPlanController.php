<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerProductPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $otherPartners = Partner::where('id', '!=', $partner->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.partners.product-plans', compact('partner', 'grouped', 'accessibleOfferings', 'otherPartners'));
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

        // Prevent the exact same offering being added twice for the same key+frequency.
        // Different offerings at the same key+frequency are allowed (fan-out).
        $exactDuplicate = PartnerProductPlan::where('partner_id', $partner->id)
            ->where('product_key', $data['product_key'])
            ->where('month_frequency', $data['month_frequency'])
            ->where('offering_id', $data['offering_id'])
            ->exists();

        if ($exactDuplicate) {
            return back()->withErrors([
                'offering_id' => "This offering is already mapped to \"{$data['product_key']}\" ({$data['month_frequency']}M).",
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

    public function copy(Request $request, int $partnerId)
    {
        $dest = Partner::findOrFail($partnerId);

        $request->validate([
            'source_partner_id' => 'required|integer|exists:partners,id',
        ]);

        $sourceId = (int) $request->input('source_partner_id');

        // Cannot copy from self.
        if ($sourceId === $dest->id) {
            return back()->with('error', 'Source and destination partner must be different.');
        }

        $source = Partner::findOrFail($sourceId);

        $sourcePlans = PartnerProductPlan::where('partner_id', $source->id)
            ->with('offering')
            ->get();

        if ($sourcePlans->isEmpty()) {
            return back()->with('error', "\"{$source->name}\" has no product plans to copy.");
        }

        // Collect unique active offering IDs referenced by the source plans.
        $sourceOfferingIds = $sourcePlans
            ->filter(fn($p) => $p->offering && $p->offering->is_active)
            ->pluck('offering_id')
            ->unique()
            ->values();

        // Grant the destination partner access to any of those offerings it
        // doesn't already have. The unique(offering_id, partner_id) DB constraint
        // is the safety net; INSERT IGNORE avoids a race-condition error.
        $now = now();
        foreach ($sourceOfferingIds as $offeringId) {
            DB::table('offering_partner')->upsert(
                [['offering_id' => $offeringId, 'partner_id' => $dest->id, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]],
                ['offering_id', 'partner_id'],
                ['is_active', 'updated_at']
            );
        }

        // Refresh accessible set after granting access.
        $accessibleOfferingIds = $dest->accessibleOfferings()
            ->where('offerings.is_active', true)
            ->pluck('offerings.id')
            ->flip();

        // Build existing destination plans as a set to detect exact duplicates.
        $existingKeys = PartnerProductPlan::where('partner_id', $dest->id)
            ->get(['product_key', 'month_frequency', 'offering_id'])
            ->map(fn($p) => "{$p->product_key}|{$p->month_frequency}|{$p->offering_id}")
            ->flip();

        $copied           = 0;
        $skippedDuplicate = 0;

        foreach ($sourcePlans as $plan) {
            if (!$accessibleOfferingIds->has($plan->offering_id)) {
                continue; // offering inactive — skip silently
            }

            // Skip exact duplicates (same key + frequency + offering already exists).
            $key = "{$plan->product_key}|{$plan->month_frequency}|{$plan->offering_id}";
            if ($existingKeys->has($key)) {
                $skippedDuplicate++;
                continue;
            }

            PartnerProductPlan::create([
                'partner_id'      => $dest->id,
                'product_key'     => $plan->product_key,
                'offering_id'     => $plan->offering_id,
                'month_frequency' => $plan->month_frequency,
                'label'           => $plan->label,
            ]);

            $copied++;
        }

        if ($copied === 0) {
            $reason = $skippedDuplicate > 0
                ? 'all plans already exist on this partner'
                : 'source partner has no active plans to copy';
            return back()->with('error', "Nothing copied — {$reason}.");
        }

        $msg = "Copied {$copied} plan(s) from \"{$source->name}\".";
        if ($skippedDuplicate > 0) {
            $msg .= " {$skippedDuplicate} skipped (already exist).";
        }

        return back()->with('success', $msg);
    }
}
