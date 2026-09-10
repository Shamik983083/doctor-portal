<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MfaCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MfaController extends Controller
{
    public function showVerify(Request $request)
    {
        if (! $request->session()->has('mfa_pending_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('mfa_pending_user_id'));
        if (! $user) {
            $request->session()->forget('mfa_pending_user_id');
            return redirect()->route('login');
        }

        return view('auth.mfa-verify', [
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $userId = $request->session()->get('mfa_pending_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $key = 'mfa-attempts:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['code' => "Too many attempts. Please wait {$seconds} seconds and try again."]);
        }

        $user = User::findOrFail($userId);

        if (
            ! $user->mfa_code
            || ! $user->mfa_expires_at
            || now()->isAfter($user->mfa_expires_at)
        ) {
            return back()->withErrors(['code' => 'Your code has expired. Please request a new one.']);
        }

        if (! Hash::check($request->code, $user->mfa_code)) {
            RateLimiter::hit($key, 300);
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        // Code accepted — clear it and complete login
        RateLimiter::clear($key);

        $user->forceFill([
            'mfa_code'       => null,
            'mfa_expires_at' => null,
        ])->save();

        $request->session()->forget('mfa_pending_user_id');
        $request->session()->regenerate();

        Auth::login($user);

        $request->session()->put('mfa_verified', true);

        if ($user->force_password_reset) {
            return redirect()->route('password.force-reset');
        }

        return redirect()->intended($this->redirectAfterLogin($user));
    }

    public function resend(Request $request)
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $key = 'mfa-resend:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->with('status', "Please wait {$seconds} seconds before requesting another code.");
        }

        RateLimiter::hit($key, 120);

        $user = User::findOrFail($userId);
        $this->sendMfaCode($user);

        return back()->with('status', 'A new code has been sent to your email.');
    }

    public function showForceReset(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! Auth::user()->force_password_reset) {
            return redirect($this->redirectAfterLogin(Auth::user()));
        }

        return view('auth.force-reset');
    }

    public function forceReset(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Your new password must be different from your current password.']);
        }

        $user->forceFill([
            'password'             => Hash::make($request->password),
            'force_password_reset' => false,
        ])->save();

        return redirect($this->redirectAfterLogin($user))->with('success', 'Password updated successfully.');
    }

    public static function sendMfaCode(User $user): void
    {
        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'mfa_code'       => Hash::make($plainCode),
            'mfa_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(new MfaCodeMail($plainCode, $user->name));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = min(3, strlen($local));
        return Str::limit($local, $visible, '') . str_repeat('*', max(0, strlen($local) - $visible)) . '@' . $domain;
    }

    private function redirectAfterLogin(User $user): string
    {
        if ($user->hasRole('super_admin'))   return '/admin/dashboard';
        if ($user->hasRole('admin'))         return '/admin/dashboard';
        if ($user->hasRole('clinician'))     return '/clinician/dashboard';
        if ($user->hasRole('partner'))       return '/partner/dashboard';
        if ($user->hasRole('support_staff')) return '/support/dashboard';
        return '/login';
    }
}
