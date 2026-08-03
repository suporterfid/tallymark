<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Auth;

final class LocalAuthenticator
{
    public function attempt(string $email, string $password, bool $remember = false): bool
    {
        return Auth::attempt(['email' => $email, 'password' => $password], $remember);
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
