<?php

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use App\Application\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
        });
    }
}
