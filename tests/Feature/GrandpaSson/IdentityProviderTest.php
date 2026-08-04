<?php

namespace Tests\Feature\GrandpaSson;

use App\Application\Auth\IdentityProvider;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class IdentityProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_provider_resolves_the_authenticated_user_without_a_tenant_restriction(): void
    {
        $user = $this->user();
        $request = Request::create('/');
        $request->setUserResolver(static fn (): User => $user);

        $provider = app(IdentityProvider::class);

        self::assertSame($user->id, $provider->resolveIdentity($request)?->id);
        self::assertNull($provider->accessibleTenantIds($request));
    }

    public function test_broker_provider_resolves_the_mirrored_user_and_its_tenant_claims_from_the_session(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $user = $this->user();
        $session = app('session.store');
        $session->flush();
        $session->put('grandpasson.identity', [
            'user_id' => $user->id,
            'tenant_public_ids' => ['ten_analytics'],
        ]);
        $request = Request::create('/');
        $request->setLaravelSession($session);

        $provider = app(IdentityProvider::class);

        self::assertSame($user->id, $provider->resolveIdentity($request)?->id);
        self::assertSame(['ten_analytics'], $provider->accessibleTenantIds($request));
    }

    private function user(): User
    {
        return User::query()->create([
            'name' => 'Broker User',
            'email' => 'broker-user-'.str()->random(8).'@example.test',
            'password' => Hash::make('password'),
        ]);
    }
}
