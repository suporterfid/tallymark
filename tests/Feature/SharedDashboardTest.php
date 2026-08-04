<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\SiteHost;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SharedDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_dashboard_exposes_only_safe_aggregate_metrics(): void
    {
        [$tenant, $user] = $this->tenantWithAdmin();
        $site = Site::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Public example',
            'timezone' => 'UTC',
            'site_key' => 'must-not-leak-site-key',
        ]);
        SiteHost::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'hostname' => 'secret-host.example.test']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'name' => 'Private signup definition', 'event_name' => 'signup']);
        DB::table('stats_daily_totals')->insert(['site_id' => $site->id, 'day' => '2026-08-03', 'pageviews' => 12, 'visitors' => 7, 'sessions' => 8, 'bounces' => 2, 'duration_sum' => 300]);
        DB::table('stats_daily_goals')->insert(['site_id' => $site->id, 'goal_id' => $goalId, 'day' => '2026-08-03', 'conversions' => 3, 'visitors' => 3]);

        $creation = $this->actingAs($user)->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/shared-dashboard');

        $creation->assertCreated();
        $publicId = $creation->json('data.public_id');
        self::assertIsString($publicId);

        $this->get('/shared/'.$publicId)
            ->assertOk()
            ->assertSee('Public example')
            ->assertSee('12')
            ->assertSee('3')
            ->assertDontSee('must-not-leak-site-key')
            ->assertDontSee('secret-host.example.test')
            ->assertDontSee('Private signup definition')
            ->assertDontSee('shared-admin@example.test');
    }

    public function test_tenant_admin_can_disable_and_rotate_a_shared_dashboard(): void
    {
        [$tenant, $user] = $this->tenantWithAdmin();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Public example', 'timezone' => 'UTC', 'site_key' => 'shared-rotate-key']);
        $endpoint = '/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/shared-dashboard';
        $dashboard = $this->actingAs($user)->postJson($endpoint)->assertCreated()->json('data');

        $this->actingAs($user)->patchJson($endpoint, ['is_enabled' => false])->assertOk();
        $this->get('/shared/'.$dashboard['public_id'])->assertNotFound();

        $rotated = $this->actingAs($user)->postJson($endpoint.'/rotate')->assertOk()->json('data');

        self::assertNotSame($dashboard['public_id'], $rotated['public_id']);
        $this->get('/shared/'.$dashboard['public_id'])->assertNotFound();
        $this->get('/shared/'.$rotated['public_id'])->assertOk();
    }

    /** @return array{Tenant, User} */
    private function tenantWithAdmin(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Shared dashboard', 'slug' => 'shared-dashboard']);
        $user = User::query()->create(['name' => 'Admin', 'email' => 'shared-admin@example.test', 'password' => Hash::make('password')]);
        TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'tenant_admin']);

        return [$tenant, $user];
    }
}
