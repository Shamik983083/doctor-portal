<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
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
        return view('admin.admins.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:admin,super_admin',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return redirect()->route('admin.admins.index')
            ->with('success', "Admin user \"{$user->name}\" created successfully.");
    }

    public function show(int $id)
    {
        $admin = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->findOrFail($id);

        return view('admin.admins.show', compact('admin'));
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
