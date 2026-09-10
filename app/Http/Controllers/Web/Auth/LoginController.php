<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Auth\MfaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->redirectAfterLogin());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            /*
             * A deactivated account must not get a session. Adding the
             * is_active column alone would have made the admin toggle persist
             * something nobody reads, which is the same lie in a tidier form.
             *
             * Checked AFTER a successful attempt, deliberately. Refusing before
             * the password is verified would tell an anonymous visitor which
             * addresses exist and which are switched off. The message stays the
             * generic credential error for the same reason.
             *
             * `?? true` keeps every pre-existing row usable: the column is new
             * and defaults to true, but a row loaded from a cache or a partial
             * select should not read as deactivated because the value is absent.
             */
            if (Auth::user()->is_active ?? true) {
                $user = Auth::user();
                Auth::logout();

                $request->session()->put('mfa_pending_user_id', $user->id);

                MfaController::sendMfaCode($user);

                return redirect()->route('mfa.verify');
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->forceFill(['remember_token' => null])->save();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    private function redirectAfterLogin(): string
    {
        $user = Auth::user();
        if ($user->hasRole('super_admin'))   return '/admin/dashboard';
        if ($user->hasRole('admin'))         return '/admin/dashboard';
        if ($user->hasRole('clinician'))     return '/clinician/dashboard';
        if ($user->hasRole('partner'))       return '/partner/dashboard';
        if ($user->hasRole('support_staff')) return '/support/dashboard';
        return '/login';
    }
}
