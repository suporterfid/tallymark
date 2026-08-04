<?php

namespace App\Policies;

use App\Application\GrandpaSson\GrandpaSsonMachineActor;
use App\Domain\Shared\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;

final class TenantPolicy
{
    public function view(User|GrandpaSsonMachineActor $user, Tenant $tenant): bool
    {
        if ($user instanceof GrandpaSsonMachineActor) {
            return true;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->memberships()
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    public function update(User|GrandpaSsonMachineActor $user, Tenant $tenant): bool
    {
        if ($user instanceof GrandpaSsonMachineActor) {
            return true;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantRole::TenantAdmin)
            ->exists();
    }
}
