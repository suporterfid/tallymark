<?php

namespace App\Application\Auth;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\Request;

interface IdentityProvider
{
    public function resolveIdentity(Request $request): ?User;

    /** @return list<string>|null Null means no delegated tenant restriction. */
    public function accessibleTenantIds(Request $request): ?array;
}
