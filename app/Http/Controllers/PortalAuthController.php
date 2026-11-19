<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PortalAuthController extends Controller
{
    public function loginForm()
    {
        return view('portal.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'E-mail ou senha inválidos.']);
        }$request->session()->regenerate();
        if (Auth::user()->role !== 'customer') {
            Auth::logout();

            return back()->withErrors(['email' => 'Este acesso não pertence ao portal do cliente.']);
        }

return redirect()->intended(route('portal.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    public function forgotForm()
    {
        return view('portal.forgot');
    }

    public function forgot(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $request->email)->where('role', 'customer')->first();
        if ($user) {
            Password::sendResetLink(['email' => $request->email]);
        }

return back()->with('status', 'Se o e-mail estiver cadastrado, enviaremos o link de recuperação.');
    }

    public function resetForm(string $token, Request $request)
    {
        return view('portal.reset', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required', 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset ? redirect()->route('portal.login')->with('status', 'Senha alterada. Entre com a nova senha.') : back()->withErrors(['email' => __($status)]);
    }
}
