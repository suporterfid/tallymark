<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class GoalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_an_event_goal(): void
    {
        [$tenant, $user] = $this->tenantWithAdmin();
        $site = Site::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Example',
            'timezone' => 'UTC',
            'site_key' => 'goal-management-key',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/goals', [
                'name' => 'Signup',
                'event_name' => 'signup',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Signup')
            ->assertJsonPath('data.event_name', 'signup')
            ->assertJsonPath('data.url_pattern', null);

        $this->assertDatabaseHas('goals', [
            'site_id' => $site->id,
            'name' => 'Signup',
            'event_name' => 'signup',
        ]);
    }

    public function test_goal_uses_exactly_one_event_or_literal_url_prefix_matcher(): void
    {
        [$tenant, $user] = $this->tenantWithAdmin();
        $site = Site::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Example',
            'timezone' => 'UTC',
            'site_key' => 'goal-matcher-key',
        ]);
        $endpoint = '/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/goals';

        $this->actingAs($user)->postJson($endpoint, [
            'name' => 'Thank you',
            'url_pattern' => '/thank-you',
        ])->assertCreated()->assertJsonPath('data.url_pattern', '/thank-you');

        $this->actingAs($user)->postJson($endpoint, [
            'name' => 'Ambiguous',
            'event_name' => 'signup',
            'url_pattern' => '/thank-you',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson($endpoint, [
            'name' => 'Invalid prefix',
            'url_pattern' => 'thank-you',
        ])->assertUnprocessable();
    }

    public function test_tenant_admin_can_list_update_and_delete_a_goal(): void
    {
        [$tenant, $user] = $this->tenantWithAdmin();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'goal-lifecycle-key']);
        $endpoint = '/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/goals';
        $goal = $this->actingAs($user)->postJson($endpoint, ['name' => 'Signup', 'event_name' => 'signup'])->assertCreated()->json('data');

        $this->actingAs($user)->getJson($endpoint)->assertOk()->assertJsonPath('data.0.public_id', $goal['public_id']);
        $this->actingAs($user)->patchJson($endpoint.'/'.$goal['public_id'], ['name' => 'Completed signup'])->assertOk()->assertJsonPath('data.name', 'Completed signup');
        $this->actingAs($user)->deleteJson($endpoint.'/'.$goal['public_id'])->assertNoContent();
        $this->assertDatabaseMissing('goals', ['id' => $goal['id']]);
    }

    /** @return array{Tenant, User} */
    private function tenantWithAdmin(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'goal-management']);
        $user = User::query()->create(['name' => 'Admin', 'email' => 'goal-admin@example.test', 'password' => Hash::make('password')]);
        TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'tenant_admin']);

        return [$tenant, $user];
    }
}
