<?php

namespace App\Http\Middleware;

use App\Application\GrandpaSson\GrandpaSsonMachineActor;
use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Application\GrandpaSson\IntrospectionResult;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateGrandpaSsonMachineToken
{
    public function __construct(private readonly IntrospectionClientInterface $introspection) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! (bool) config('grandpasson.inbound_enabled', false)
            || ! is_string($token)
            || ! str_starts_with($token, 'gpat_live_')) {
            return $next($request);
        }

        try {
            $result = $this->introspection->introspect($token);
        } catch (\Throwable) {
            $result = new IntrospectionResult(active: false);
        }

        $request->attributes->set('grandpasson.machine_actor', new GrandpaSsonMachineActor(
            $result,
            hash('sha256', $token),
        ));

        return $next($request);
    }
}
