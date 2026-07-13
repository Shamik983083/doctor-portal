<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function showForm()
    {
        if (Auth::check()) {
            return redirect($this->redirectAfterLogin());
        }

        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'Please wait before requesting another reset link.']);
        }

        // Return the same message whether the email exists or not to prevent
        // user enumeration attacks.
        return back()->with('status', 'If that email is registered, a password reset link has been sent. Check your inbox.');
    }

    private function redirectAfterLogin(): string
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return '/admin/dashboard';
        if ($user->hasRole('clinician')) return '/clinician/dashboard';
        if ($user->hasRole('partner')) return '/partner/dashboard';
        return '/';
    }
}
