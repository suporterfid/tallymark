<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\LocalAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

final class SessionController extends Controller
{
    public function store(Request $request, LocalAuthenticator $authenticator): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $authenticator->attempt($credentials['email'], $credentials['password'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        $request->session()->regenerate();

        return redirect('/');
    }

    public function destroy(Request $request, LocalAuthenticator $authenticator): RedirectResponse
    {
        $authenticator->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
