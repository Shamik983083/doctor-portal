<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInstructionExample;
use App\Models\AiInstructionSet;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiController extends Controller
{
    private function contexts(): array
    {
        return array_keys(config('ai.contexts', []));
    }

    private function assertContext(string $context): void
    {
        abort_unless(in_array($context, $this->contexts(), true), 404);
    }

    public function index()
    {
        $contextLabels = config('ai.contexts', []);

        $sets = AiInstructionSet::with(['updatedBy'])
            ->where('is_active', true)
            ->withCount('examples')
            ->get()
            ->keyBy('context');

        return view('admin.ai.index', compact('contextLabels', 'sets'));
    }

    public function edit(string $context)
    {
        $this->assertContext($context);

        $contextLabels = config('ai.contexts', []);
        $label         = $contextLabels[$context];

        $set = AiInstructionSet::with([
            'examples' => fn ($q) => $q->orderBy('sort_order'),
            'updatedBy',
        ])
            ->where('context', $context)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        return view('admin.ai.edit', compact('context', 'label', 'set'));
    }

    public function update(Request $request, string $context)
    {
        $this->assertContext($context);

        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'instructions' => 'required|string|max:8000',
            'tone'         => 'nullable|string|max:200',
        ]);

        $set = AiInstructionSet::where('context', $context)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($set) {
            $set->update([
                'name'         => $data['name'],
                'instructions' => $data['instructions'],
                'tone'         => $data['tone'] ?? null,
                'version'      => $set->version + 1,
                'updated_by'   => Auth::id(),
            ]);
        } else {
            AiInstructionSet::create([
                'context'      => $context,
                'name'         => $data['name'],
                'instructions' => $data['instructions'],
                'tone'         => $data['tone'] ?? null,
                'version'      => 1,
                'is_active'    => true,
                'sort_order'   => 0,
                'updated_by'   => Auth::id(),
            ]);
        }

        return back()->with('success', 'Instruction set saved.');
    }

    public function storeExample(Request $request, string $context)
    {
        $this->assertContext($context);

        $data = $request->validate([
            'situation'   => 'required|string|max:2000',
            'good_output' => 'required|string|max:4000',
            'bad_output'  => 'nullable|string|max:4000',
        ]);

        $set = AiInstructionSet::where('context', $context)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if (! $set) {
            return back()->withErrors(['situation' => 'Save the instruction set first before adding examples.']);
        }

        $maxOrder = $set->examples()->max('sort_order') ?? 0;

        $set->examples()->create([
            'situation'   => $data['situation'],
            'good_output' => $data['good_output'],
            'bad_output'  => $data['bad_output'] ?? null,
            'sort_order'  => $maxOrder + 10,
        ]);

        return back()->with('success', 'Example added.');
    }

    public function destroyExample(string $context, AiInstructionExample $example)
    {
        $this->assertContext($context);

        $set = $example->instructionSet;
        abort_unless($set && $set->context === $context && $set->is_active, 403);

        $example->delete();

        return back()->with('success', 'Example removed.');
    }

    public function settings()
    {
        $integrations        = config('ai.integrations', []);
        $contextIntegrations = config('ai.context_integrations', []);
        $contextLabels       = config('ai.contexts', []);

        // Overlay DB settings for enabled/baa_confirmed per integration.
        $dbSettings = [];
        foreach ($integrations as $key => $cfg) {
            $dbEnabled = Setting::get("ai.integrations.{$key}.enabled");
            $dbBaa     = Setting::get("ai.integrations.{$key}.baa_confirmed");
            $dbSettings[$key] = [
                'enabled'       => $dbEnabled !== null ? (bool) $dbEnabled : (bool) ($cfg['enabled'] ?? false),
                'baa_confirmed' => $dbBaa     !== null ? (bool) $dbBaa     : (bool) ($cfg['baa_confirmed'] ?? false),
            ];
        }

        return view('admin.ai.settings', compact(
            'integrations', 'contextIntegrations', 'contextLabels', 'dbSettings'
        ));
    }

    public function updateSettings(Request $request, string $integration)
    {
        abort_unless(array_key_exists($integration, config('ai.integrations', [])), 404);

        $data = $request->validate([
            'enabled'          => 'nullable|boolean',
            'baa_confirmed'    => 'nullable|boolean',
            'baa_confirm_text' => 'nullable|string|max:20',
        ]);

        $wantEnabled = $request->boolean('enabled');
        $wantBaa     = $request->boolean('baa_confirmed');

        // BAA can only be activated when the user types the exact confirmation phrase.
        if ($wantBaa) {
            $confirmed = strtoupper(trim($data['baa_confirm_text'] ?? '')) === 'I CONFIRM';
            if (! $confirmed) {
                return back()->withErrors([
                    'baa_confirm_text' => 'Type "I CONFIRM" (all caps) to activate BAA confirmation.',
                ])->withInput();
            }
        }

        Setting::set("ai.integrations.{$integration}.enabled",       $wantEnabled ? '1' : '0');
        Setting::set("ai.integrations.{$integration}.baa_confirmed",  $wantBaa     ? '1' : '0');

        return back()->with('success', ucfirst($integration) . ' integration settings saved.');
    }
}
