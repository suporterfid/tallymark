<?php

namespace Tests\Feature\GrandpaSson;

use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Application\GrandpaSson\IntrospectionResult;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GrandpaSsonMachineAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_machine_token_with_read_scope_and_tenant_audience_can_read_api_data(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $tenant = Tenant::query()->create(['name' => 'Analytics', 'slug' => 'analytics']);
        $this->app->instance(IntrospectionClientInterface::class, new class($tenant->public_id) implements IntrospectionClientInterface
        {
            public function __construct(private readonly string $tenantPublicId) {}

            public function introspect(string $token): IntrospectionResult
            {
                return new IntrospectionResult(
                    active: true,
                    scopes: ['analytics:read'],
                    audiences: ['workspace/'.$this->tenantPublicId],
                );
            }
        });

        $this->withHeader('Authorization', 'Bearer gpat_live_example')
            ->getJson('/api/v1/tenants/'.$tenant->public_id.'/audit-logs')
            ->assertOk();
    }

    public function test_machine_token_with_wrong_audience_is_denied_and_audited_without_the_raw_token(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $tenant = Tenant::query()->create(['name' => 'Analytics', 'slug' => 'analytics']);
        $token = 'gpat_live_example';
        $this->app->instance(IntrospectionClientInterface::class, new class implements IntrospectionClientInterface
        {
            public function introspect(string $token): IntrospectionResult
            {
                return new IntrospectionResult(active: true, scopes: ['analytics:read'], audiences: ['workspace/ten_other']);
            }
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/tenants/'.$tenant->public_id.'/audit-logs')
            ->assertForbidden();

        $audit = AuditLog::withoutGlobalScopes()->where('action', 'grandpasson.machine_denied')->firstOrFail();
        self::assertSame(hash('sha256', $token), $audit->summary_json['token_fingerprint']);
        self::assertNotContains($token, $audit->summary_json);
        self::assertSame('audience_mismatch', $audit->summary_json['reason']);
    }

    public function test_active_machine_token_with_write_scope_can_mutate_tenant_data(): void
    {
        config()->set('grandpasson.inbound_enabled', true);
        $tenant = Tenant::query()->create(['name' => 'Analytics', 'slug' => 'analytics']);
        $this->app->instance(IntrospectionClientInterface::class, new class($tenant->public_id) implements IntrospectionClientInterface
        {
            public function __construct(private readonly string $tenantPublicId) {}

            public function introspect(string $token): IntrospectionResult
            {
                return new IntrospectionResult(
                    active: true,
                    scopes: ['analytics:write'],
                    audiences: [$this->tenantPublicId],
                );
            }
        });

        $this->withHeader('Authorization', 'Bearer gpat_live_example')
            ->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites', [
                'name' => 'Machine site',
                'timezone' => 'UTC',
                'hosts' => ['machine.example.test'],
            ])
            ->assertCreated();
    }
}
