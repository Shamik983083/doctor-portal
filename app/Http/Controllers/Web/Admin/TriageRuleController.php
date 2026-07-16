<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\TriageRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TriageRuleController extends Controller
{
    public function index()
    {
        $rules = TriageRule::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.triage.index', [
            'bmiRules'      => $rules->where('type', 'bmi_threshold')->values(),
            'ageRules'      => $rules->where('type', 'age_threshold')->values(),
            'keywordRules'  => $rules->where('type', 'keyword')->values(),
            'offeringRules' => $rules->where('type', 'offering')->values(),
            'version'       => config('triage.version', 'triage-v1'),
            'cfg'           => config('triage'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'          => 'required|in:bmi_threshold,age_threshold,keyword,offering',
            'operator'      => 'nullable|in:gte,lte,gt,lt',
            'value'         => 'required|string|max:255',
            'triage_result' => 'required|in:red,yellow',
            'label'         => 'nullable|string|max:255',
            'is_active'     => 'nullable',
        ]);

        // keyword and offering always use contains; threshold rules require operator
        $data['operator'] = in_array($data['type'], ['keyword', 'offering'])
            ? 'contains'
            : ($data['operator'] ?? 'gte');

        $data['label']     = $data['label'] ?: $this->autoLabel($data['type'], $data['operator'], $data['value'], $data['triage_result']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (TriageRule::where('type', $data['type'])->max('sort_order') ?? 0) + 10;

        unset($data[0]);  // remove any numeric key from validate array

        TriageRule::create($data);
        $this->clearCache();

        return back()->with('success', 'Triage rule added.');
    }

    public function update(Request $request, TriageRule $triageRule)
    {
        $data = $request->validate([
            'type'          => 'required|in:bmi_threshold,age_threshold,keyword,offering',
            'operator'      => 'nullable|in:gte,lte,gt,lt',
            'value'         => 'required|string|max:255',
            'triage_result' => 'required|in:red,yellow',
            'label'         => 'nullable|string|max:255',
            'is_active'     => 'nullable',
        ]);

        $data['operator'] = in_array($data['type'], ['keyword', 'offering'])
            ? 'contains'
            : ($data['operator'] ?? 'gte');

        $data['label']     = $data['label'] ?: $this->autoLabel($data['type'], $data['operator'], $data['value'], $data['triage_result']);
        $data['is_active'] = $request->boolean('is_active', true);

        $triageRule->update($data);
        $this->clearCache();

        return back()->with('success', 'Triage rule updated.');
    }

    public function destroy(TriageRule $triageRule)
    {
        $triageRule->delete();
        $this->clearCache();

        return back()->with('success', 'Triage rule deleted.');
    }

    public function toggleActive(TriageRule $triageRule)
    {
        $triageRule->update(['is_active' => ! $triageRule->is_active]);
        $this->clearCache();

        return back()->with('success', $triageRule->is_active ? 'Rule activated.' : 'Rule deactivated.');
    }

    // ────────────────────────────────────────────────────────────────────────

    private function autoLabel(string $type, string $operator, string $value, string $result): string
    {
        $sym = match ($operator) {
            'gte'  => '≥',
            'lte'  => '≤',
            'gt'   => '>',
            'lt'   => '<',
            default => $operator,
        };

        $band = strtoupper($result);

        return match ($type) {
            'bmi_threshold' => "BMI {$sym} {$value} → {$band}",
            'age_threshold' => "Age {$sym} {$value} → {$band}",
            'keyword'       => "Keyword: {$value}",
            'offering'      => "Offering: {$value}",
            default         => "{$type}: {$value}",
        };
    }

    private function clearCache(): void
    {
        Cache::forget('triage_rules_active');
    }
}
