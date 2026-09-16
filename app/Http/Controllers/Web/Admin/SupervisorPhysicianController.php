<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupervisorPhysician;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SupervisorPhysicianController extends Controller
{
    public function index(Request $request)
    {
        $physicians = User::with('supervisorPhysician')
            ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor_physician'))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.supervisor-physicians.index', compact('physicians'));
    }

    public function create()
    {
        return view('admin.supervisor-physicians.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'npi'                   => 'required|string|max:20',
            'licensed_states'       => 'required|array|min:1',
            'licensed_states.*'     => 'string|size:2',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'force_password_reset' => true,
        ]);

        $user->assignRole('supervisor_physician');

        SupervisorPhysician::create([
            'user_id'         => $user->id,
            'npi'             => $data['npi'] ?? null,
            'licensed_states' => array_values(array_unique($data['licensed_states'])),
        ]);

        return redirect()->route('admin.supervisor-physicians.index')
            ->with('success', "Supervisor Physician \"{$user->name}\" created successfully.");
    }

    public function show(int $id)
    {
        $user = User::with('supervisorPhysician')
            ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor_physician'))
            ->findOrFail($id);

        return view('admin.supervisor-physicians.show', compact('user'));
    }

    public function update(Request $request, int $id)
    {
        $user = User::with('supervisorPhysician')
            ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor_physician'))
            ->findOrFail($id);

        $data = $request->validate([
            'npi'               => 'required|string|max:20',
            'licensed_states'   => 'required|array|min:1',
            'licensed_states.*' => 'string|size:2',
        ]);

        $profile = $user->supervisorPhysician ?? new SupervisorPhysician(['user_id' => $user->id]);
        $profile->npi             = $data['npi'] ?? null;
        $profile->licensed_states = array_values(array_unique($data['licensed_states']));
        $profile->save();

        return back()->with('success', 'Profile updated.');
    }

    public function toggleActive(int $id, Request $request)
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'supervisor_physician'))
            ->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $wasActive = $user->is_active ?? true;
        $user->update(['is_active' => ! $wasActive]);

        return back()->with('success', $wasActive
            ? "\"{$user->name}\" deactivated."
            : "\"{$user->name}\" reactivated.");
    }

    public function destroy(int $id, Request $request)
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'supervisor_physician'))
            ->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.supervisor-physicians.index')
            ->with('success', "\"{$user->name}\" deleted.");
    }
}
