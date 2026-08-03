<?php

namespace Tests\Feature;

use App\Application\Tenancy\TenantContext;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_member_cannot_read_another_tenants_audit_logs(): void
    {
        [$firstTenant, $firstUser] = $this->tenantWithMember('First tenant', 'first@example.test');
        [$secondTenant] = $this->tenantWithMember('Second tenant', 'second@example.test');

        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $firstTenant->id,
            'action' => 'first.action',
            'resource_type' => 'tenant',
        ]);
        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $secondTenant->id,
            'action' => 'second.action',
            'resource_type' => 'tenant',
        ]);

        $this->actingAs($firstUser)
            ->getJson('/api/v1/tenants/'.$secondTenant->public_id.'/audit-logs')
            ->assertNotFound();

        $this->actingAs($firstUser)
            ->getJson('/api/v1/tenants/'.$firstTenant->public_id.'/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'first.action')
            ->assertJsonCount(1, 'data');
    }

    public function test_audit_logs_are_always_limited_to_the_active_tenant_context(): void
    {
        [$firstTenant] = $this->tenantWithMember('First tenant', 'first@example.test');
        [$secondTenant] = $this->tenantWithMember('Second tenant', 'second@example.test');

        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $firstTenant->id,
            'action' => 'first.action',
            'resource_type' => 'tenant',
        ]);
        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $secondTenant->id,
            'action' => 'second.action',
            'resource_type' => 'tenant',
        ]);

        $context = app(TenantContext::class);
        $context->set($firstTenant);
        self::assertSame(['first.action'], AuditLog::query()->pluck('action')->all());

        $context->set($secondTenant);
        self::assertSame(['second.action'], AuditLog::query()->pluck('action')->all());

        $context->clear();
        self::assertSame([], AuditLog::query()->pluck('action')->all());
    }

    public function test_local_login_authenticates_a_user(): void
    {
        $user = User::query()->create([
            'name' => 'Local User',
            'email' => 'local@example.test',
            'password' => Hash::make('password'),
        ]);

        $csrfToken = 'test-csrf-token';

        $this->withSession(['_token' => $csrfToken])
            ->post('/login', [
                '_token' => $csrfToken,
                'email' => 'local@example.test',
                'password' => 'password',
            ])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_bootstrap_admin_command_creates_only_the_first_platform_admin(): void
    {
        $this->artisan('platform:bootstrap-admin', [
            'email' => 'admin@example.test',
            'password' => 'bootstrap-secret',
            '--name' => 'Bootstrap Admin',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        self::assertTrue($admin->isPlatformAdmin());
        self::assertStringStartsWith('usr_', $admin->public_id);

        $this->artisan('platform:bootstrap-admin', [
            'email' => 'another-admin@example.test',
            'password' => 'bootstrap-secret',
        ])->assertFailed();
    }

    /** @return array{Tenant, User} */
    private function tenantWithMember(string $tenantName, string $email): array
    {
        $tenant = Tenant::query()->create([
            'name' => $tenantName,
            'slug' => str($tenantName)->slug(),
        ]);
        $user = User::query()->create([
            'name' => $tenantName.' member',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'tenant_admin',
        ]);

        return [$tenant, $user];
    }
}
