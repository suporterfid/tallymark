<?php

namespace App\Http\Middleware;

use App\Application\Auth\IdentityProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromUser
{
    public function __construct(private readonly IdentityProvider $identityProvider) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->identityProvider->resolveIdentity($request);
        App::setLocale($user?->locale ?? config('app.locale'));

        return $next($request);
    }
}
