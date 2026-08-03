<?php

namespace App\Http\Middleware;

use App\Application\Tenancy\TenantContext;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeTenant = $request->route('tenant');
        $tenant = $routeTenant instanceof Tenant
            ? $routeTenant
            : Tenant::query()->where('public_id', $routeTenant)->firstOrFail();

        if ($user === null) {
            abort(401);
        }

        if (! $user->can('view', $tenant)) {
            abort(404);
        }

        $this->context->set($tenant);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
