<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserRosterController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['roles', 'clinician', 'partner'])
            ->when($request->input('search'), fn ($q, $s) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                )
            )
            ->when($request->input('role'), fn ($q, $r) =>
                $q->whereHas('roles', fn ($q) => $q->where('name', $r))
            )
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }
}
