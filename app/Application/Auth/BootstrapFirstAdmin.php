<?php

namespace App\Application\Auth;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Support\Facades\Hash;

final class BootstrapFirstAdmin
{
    public function createAdmin(string $email, string $password, string $name = 'Platform Admin'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_platform_admin' => true,
        ]);
    }
}
