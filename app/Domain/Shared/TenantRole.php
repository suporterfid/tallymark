<?php

namespace App\Domain\Shared;

enum TenantRole: string
{
    case TenantAdmin = 'tenant_admin';
    case TenantMember = 'tenant_member';
    case ReadOnlyViewer = 'read_only_viewer';

    public function canManageTenant(): bool
    {
        return $this === self::TenantAdmin;
    }
}
