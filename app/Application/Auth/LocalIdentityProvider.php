<?php

namespace App\Application\Auth;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\Request;

final class LocalIdentityProvider implements IdentityProvider
{
    public function resolveIdentity(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    public function accessibleTenantIds(Request $request): ?array
    {
        return null;
    }
}
