<?php

namespace Tests\Feature\GrandpaSson;

use App\Application\GrandpaSson\GrandpaSsonSession;
use App\Application\GrandpaSson\SessionExchangeClientInterface;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class GrandpaSsonLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_to_the_selected_broker_provider_with_a_session_bound_state(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        config()->set('grandpasson.base_url', 'https://broker.example.test');
        config()->set('grandpasson.browser_client_id', 'tallymark-browser');
        config()->set('grandpasson.redirect_uri', 'https://analytics.example.test/auth/grandpasson/callback');
        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('@1800000000')));

        $response = $this->get('/auth/grandpasson/login/google');

        $response->assertRedirectContains('https://broker.example.test/login/google?');
        $response->assertSessionHas('grandpasson.login_state', static function (array $state): bool {
            return is_string($state['value'] ?? null)
                && strlen($state['value']) === 64
                && ($state['expires_at'] ?? null) === 1_800_000_600;
        });
    }

    public function test_it_provisions_the_broker_subject_and_maps_only_explicit_group_roles(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $tenant = Tenant::query()->create(['name' => 'Analytics', 'slug' => 'analytics']);
        $otherTenant = Tenant::query()->create(['name' => 'Other', 'slug' => 'other']);
        config()->set('grandpasson.group_role_map', [
            $tenant->public_id => ['analytics-viewer' => 'read_only_viewer'],
        ]);
        $this->app->instance(SessionExchangeClientInterface::class, new class($tenant->public_id, $otherTenant->public_id) implements SessionExchangeClientInterface
        {
            public function __construct(
                private readonly string $tenantPublicId,
                private readonly string $otherTenantPublicId,
            ) {}

            public function exchange(string $code, ?string $tenant = null): GrandpaSsonSession
            {
                return new GrandpaSsonSession(
                    subjectId: 'usr_broker',
                    email: 'member@example.test',
                    name: 'Broker Member',
                    identityProvider: 'google',
                    tenantId: $this->tenantPublicId,
                    tenants: [['id' => $this->tenantPublicId], ['id' => $this->otherTenantPublicId]],
                    groups: ['analytics-viewer'],
                    scopes: ['openid'],
                );
            }
        });

        $this->withSession([
            'grandpasson.login_state' => ['value' => 'expected-state', 'expires_at' => PHP_INT_MAX],
        ])->get('/auth/grandpasson/callback?code=broker-code&state=expected-state')
            ->assertRedirect('/');

        $user = User::query()->where('email', 'member@example.test')->firstOrFail();
        self::assertFalse($user->isPlatformAdmin());
        self::assertDatabaseHas(TenantMembership::class, [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'read_only_viewer',
        ]);
        self::assertDatabaseMissing(TenantMembership::class, [
            'tenant_id' => $otherTenant->id,
            'user_id' => $user->id,
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_state_mismatch_fails_closed_without_redeeming_the_code(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        Http::fake();

        $this->withSession([
            'grandpasson.login_state' => ['value' => 'expected-state', 'expires_at' => 1_800_000_600],
        ])->get('/auth/grandpasson/callback?code=broker-code&state=wrong-state')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_it_revokes_a_prior_broker_membership_when_no_active_tenant_group_is_mapped(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $tenant = Tenant::query()->create(['name' => 'Analytics', 'slug' => 'analytics']);
        $user = User::query()->create([
            'name' => 'Broker Member',
            'email' => 'member@example.test',
            'password' => 'not-used',
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'tenant_member',
            'identity_provider' => 'grandpasson',
        ]);
        $this->app->instance(SessionExchangeClientInterface::class, new class($tenant->public_id) implements SessionExchangeClientInterface
        {
            public function __construct(private readonly string $tenantPublicId) {}

            public function exchange(string $code, ?string $tenant = null): GrandpaSsonSession
            {
                return new GrandpaSsonSession(
                    subjectId: 'usr_broker',
                    email: 'member@example.test',
                    name: 'Broker Member',
                    identityProvider: 'google',
                    tenantId: $this->tenantPublicId,
                    tenants: [['id' => $this->tenantPublicId]],
                    groups: [],
                    scopes: ['openid'],
                );
            }
        });

        $this->withSession([
            'grandpasson.login_state' => ['value' => 'expected-state', 'expires_at' => PHP_INT_MAX],
        ])->get('/auth/grandpasson/callback?code=broker-code&state=expected-state')
            ->assertRedirect('/');

        self::assertDatabaseMissing(TenantMembership::class, [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_expired_state_fails_closed_without_redeeming_the_code(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('@1800000601')));
        Http::fake();

        $this->withSession([
            'grandpasson.login_state' => ['value' => 'expected-state', 'expires_at' => 1_800_000_600],
        ])->get('/auth/grandpasson/callback?code=broker-code&state=expected-state')
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
