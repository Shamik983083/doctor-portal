<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $admins = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($request->role, fn($q, $r) => $q->whereHas('roles', fn($q) => $q->where('name', $r)))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.admins.index', compact('admins'));
    }

    public function create()
    {
        return view('admin.admins.create', [
            'clinicians' => Clinician::with('user')->orderBy('priority')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:admin,super_admin',
            'clinician_ids'   => 'nullable|array',
            'clinician_ids.*' => 'integer|exists:clinicians,id',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        $this->syncManagedClinicians($user, $request);

        return redirect()->route('admin.admins.index')
            ->with('success', "Admin user \"{$user->name}\" created successfully.")
            ->with('warning', $this->scopeWarning($user));
    }

    /**
     * Set which doctors this admin is over (Devin msg 2117).
     *
     * Super admins are intentionally NOT given rows here. They see everything and
     * `User::visibleClinicianIds()` returns null for them regardless. Storing a
     * doctor list against a super admin would create a second, contradictory
     * source of truth that looks meaningful and is not.
     */
    public function updateClinicians(Request $request, int $id)
    {
        $request->validate([
            'clinician_ids'   => 'nullable|array',
            'clinician_ids.*' => 'integer|exists:clinicians,id',
        ]);

        $admin = User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->findOrFail($id);

        $this->syncManagedClinicians($admin, $request);

        return redirect()->route('admin.admins.show', $id)
            ->with('success', 'Doctors updated for ' . $admin->name . '.')
            ->with('warning', $this->scopeWarning($admin));
    }

    private function syncManagedClinicians(User $admin, Request $request): void
    {
        if ($admin->isSuperAdmin()) {
            $admin->managedClinicians()->sync([]);   // sees everything anyway
            return;
        }

        $admin->managedClinicians()->sync($request->input('clinician_ids', []));
    }

    /**
     * A Doctor Admin over nobody sees nothing. That is the safe behaviour, but
     * from the outside it looks like a broken dashboard rather than an unfinished
     * setup, so say it plainly at the moment it happens.
     */
    private function scopeWarning(User $admin): ?string
    {
        if ($admin->isSuperAdmin()) {
            return null;
        }

        if ($admin->managedClinicians()->count() === 0) {
            return 'This admin is not over any doctors yet, so they will see no cases at all. '
                . 'Assign doctors on their detail page.';
        }

        return null;
    }

    public function show(int $id)
    {
        $admin = User::with(['roles', 'managedClinicians.user'])
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->findOrFail($id);

        return view('admin.admins.show', [
            'admin'      => $admin,
            'clinicians' => Clinician::with('user')->orderBy('priority')->get(),
        ]);
    }

    public function toggleActive(int $id, Request $request)
    {
        $admin = User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->findOrFail($id);

        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $admin->update(['is_active' => !($admin->is_active ?? true)]);

        return back()->with('success', 'Admin account status updated.');
    }

    public function promote(int $id, Request $request)
    {
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->findOrFail($id);

        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'Use the user management screen to change your own role.');
        }

        $admin->syncRoles(['super_admin']);

        return back()->with('success', "\"{$admin->name}\" promoted to Super Admin.");
    }

    public function demote(int $id, Request $request)
    {
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->findOrFail($id);

        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'You cannot demote your own account.');
        }

        $admin->syncRoles(['admin']);

        return back()->with('success', "\"{$admin->name}\" demoted to Admin.");
    }

    public function destroy(int $id, Request $request)
    {
        $admin = User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->findOrFail($id);

        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $admin->delete();

        return redirect()->route('admin.admins.index')
            ->with('success', "Admin user \"{$admin->name}\" deleted.");
    }
}
