<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token)
    {
        if (Auth::check()) {
            return redirect($this->redirectAfterLogin());
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // The User model casts 'password' as 'hashed', so no Hash::make() needed.
                $user->forceFill(['password' => $password])->save();
                $user->setRememberToken(Str::random(60));
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Your password has been reset. Please sign in with your new password.');
        }

        $message = match ($status) {
            Password::INVALID_TOKEN   => 'This reset link is invalid or has expired. Please request a new one.',
            Password::RESET_THROTTLED => 'Please wait before requesting another password reset.',
            default                   => 'Something went wrong. Please try again.',
        };

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
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
