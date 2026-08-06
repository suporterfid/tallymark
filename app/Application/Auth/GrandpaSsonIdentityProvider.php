<?php

namespace App\Application\Auth;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\Request;

final class GrandpaSsonIdentityProvider implements IdentityProvider
{
    public function __construct(private readonly LocalIdentityProvider $local) {}

    public function resolveIdentity(Request $request): ?User
    {
        $identity = $request->hasSession() ? $request->session()->get('grandpasson.identity') : null;
        $userId = is_array($identity) ? ($identity['user_id'] ?? null) : null;
        if (is_numeric($userId)) {
            return User::query()->find((int) $userId);
        }

        return $this->local->resolveIdentity($request);
    }

    public function accessibleTenantIds(Request $request): ?array
    {
        $identity = $request->hasSession() ? $request->session()->get('grandpasson.identity') : null;
        $tenantIds = is_array($identity) ? ($identity['tenant_public_ids'] ?? null) : null;

        if (! is_array($tenantIds)) {
            return $this->local->accessibleTenantIds($request);
        }

        return array_values(array_unique(array_filter($tenantIds, 'is_string')));
    }
}
