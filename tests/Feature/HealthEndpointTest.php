<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_read_authenticated_operational_freshness_and_buffer_state(): void
    {
        $now = new DateTimeImmutable('2026-08-04 12:00:00 UTC');
        $this->app->instance(Clock::class, new FixedClock($now));
        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $user = User::query()->create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => Hash::make('password'), 'is_platform_admin' => true]);
        TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'tenant_admin']);
        DB::table('system_heartbeats')->insert([
            ['name' => 'analytics:ingest', 'status' => 'healthy', 'last_seen_at' => '2026-08-04 11:59:00', 'message' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'analytics:rollup', 'status' => 'alarm', 'last_seen_at' => '2026-08-04 11:55:00', 'message' => 'Rollup failed.', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $bufferDirectory = storage_path('tm-buffer');
        if (! is_dir($bufferDirectory)) {
            mkdir($bufferDirectory, 0775, true);
        }
        file_put_contents($bufferDirectory.'/202608041157-0.ndjson', "{}\n");
        file_put_contents($bufferDirectory.'/202608041158-1.ndjson', "{}\n");
        file_put_contents($bufferDirectory.'/tm-shed.count', "1\n1\n");

        try {
            $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/health')
                ->assertOk()
                ->assertJsonPath('data.ingest.fresh', true)
                ->assertJsonPath('data.rollup.fresh', false)
                ->assertJsonPath('data.buffer_depth', 2)
                ->assertJsonPath('data.shed_events', 2);
        } finally {
            @unlink($bufferDirectory.'/202608041157-0.ndjson');
            @unlink($bufferDirectory.'/202608041158-1.ndjson');
            @unlink($bufferDirectory.'/tm-shed.count');
        }
    }

    public function test_regular_tenant_member_cannot_read_instance_wide_operational_state(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $user = User::query()->create(['name' => 'Member', 'email' => 'member@example.test', 'password' => Hash::make('password')]);
        TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'tenant_admin']);

        $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/health')->assertForbidden();
    }
}
