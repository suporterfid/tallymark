<?php

namespace App\Http\Middleware;

use App\Application\Auth\IdentityProvider;
use App\Application\GrandpaSson\GrandpaSsonMachineActor;
use App\Application\Tenancy\TenantContext;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly IdentityProvider $identityProvider,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeTenant = $request->route('tenant');
        $tenant = $routeTenant instanceof Tenant
            ? $routeTenant
            : Tenant::query()->where('public_id', $routeTenant)->firstOrFail();

        $actor = $request->attributes->get('grandpasson.machine_actor');
        if ($actor instanceof GrandpaSsonMachineActor) {
            return $this->authorizeMachineActor($request, $next, $tenant, $actor);
        }

        $user = $this->identityProvider->resolveIdentity($request);

        if ($user === null) {
            abort(401);
        }

        $accessibleTenantIds = $this->identityProvider->accessibleTenantIds($request);
        if ($accessibleTenantIds !== null && ! in_array($tenant->public_id, $accessibleTenantIds, true)) {
            abort(404);
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

    private function authorizeMachineActor(
        Request $request,
        Closure $next,
        Tenant $tenant,
        GrandpaSsonMachineActor $actor,
    ): Response {
        $requiredScope = $request->isMethod('GET')
            ? (string) config('grandpasson.read_scope', 'analytics:read')
            : (string) config('grandpasson.write_scope', 'analytics:write');
        $result = $actor->introspection;
        $reason = ! $result->active
            ? 'inactive_token'
            : (! $result->hasScope($requiredScope)
                ? 'missing_scope'
                : (! $result->audienceIncludes($tenant->public_id) ? 'audience_mismatch' : null));

        if ($reason !== null) {
            AuditLog::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'action' => 'grandpasson.machine_denied',
                'resource_type' => 'tenant',
                'resource_id' => $tenant->public_id,
                'summary_json' => [
                    'reason' => $reason,
                    'required_scope' => $requiredScope,
                    'scopes' => $result->scopes,
                    'audiences' => $result->audiences,
                    'token_fingerprint' => $actor->tokenFingerprint,
                ],
            ]);

            abort($reason === 'inactive_token' ? 401 : 403);
        }

        $this->context->set($tenant);
        Auth::setUser($actor);

        try {
            return $next($request);
        } finally {
            Auth::forgetUser();
            $this->context->clear();
        }
    }
}
