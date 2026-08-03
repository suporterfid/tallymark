<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SiteMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_mutations_atomically_regenerate_a_map_with_the_current_salt_after_pr4(): void
    {
        [$tenant, $user] = $this->tenantAdministrator();

        $created = $this->actingAs($user)->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites', [
            'name' => 'Example site',
            'timezone' => 'America/Sao_Paulo',
            'hosts' => ['example.test'],
        ])->assertCreated();

        $siteId = $created->json('data.public_id');
        $site = Site::withoutGlobalScopes()->where('public_id', $siteId)->firstOrFail();
        $originalKey = $site->site_key;

        $this->assertMapContains($originalKey, $site->id, ['example.test'], 100);

        $this->actingAs($user)->patchJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$siteId, [
            'sample' => 25,
        ])->assertOk();
        $this->assertMapContains($originalKey, $site->id, ['example.test'], 25);

        $hostId = $this->actingAs($user)->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$siteId.'/hosts', [
            'hostname' => 'www.example.test',
        ])->assertCreated()->json('data.public_id');
        $this->assertMapContains($originalKey, $site->id, ['example.test', 'www.example.test'], 25);

        $this->actingAs($user)
            ->deleteJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$siteId.'/hosts/'.$hostId)
            ->assertNoContent();
        $this->assertMapContains($originalKey, $site->id, ['example.test'], 25);

        $this->actingAs($user)->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$siteId.'/hosts', [
            'hostname' => 'www.example.test',
        ])->assertCreated();
        $this->assertMapContains($originalKey, $site->id, ['example.test', 'www.example.test'], 25);

        $rotated = $this->actingAs($user)
            ->postJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$siteId.'/rotate-key')
            ->assertOk();
        $rotatedKey = $rotated->json('data.site_key');

        self::assertNotSame($originalKey, $rotatedKey);
        self::assertSame($site->id, Site::withoutGlobalScopes()->where('public_id', $siteId)->value('id'));
        $map = $this->siteMap();
        self::assertArrayNotHasKey($originalKey, $map['sites']);
        $this->assertMapContains($rotatedKey, $site->id, ['example.test', 'www.example.test'], 25);

        $this->actingAs($user)
            ->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $siteId);

        $this->actingAs($user)
            ->deleteJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$siteId)
            ->assertNoContent();

        self::assertSame([], $this->siteMap()['sites']);
    }

    /** @return array{Tenant, User} */
    private function tenantAdministrator(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Example tenant',
            'slug' => 'example-tenant',
        ]);
        $user = User::query()->create([
            'name' => 'Tenant admin',
            'email' => 'tenant-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'tenant_admin',
        ]);

        return [$tenant, $user];
    }

    /** @param list<string> $hosts */
    private function assertMapContains(string $siteKey, int $siteId, array $hosts, int $sample): void
    {
        $map = $this->siteMap();

        self::assertArrayHasKey('salt', $map);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $map['salt']);
        self::assertSame([
            'id' => $siteId,
            'hosts' => $hosts,
            'sample' => $sample,
            'validate_host' => true,
        ], $map['sites'][$siteKey]);
    }

    /** @return array{salt: string, sites: array<string, array{id: int, hosts: list<string>, sample: int, validate_host: bool}>} */
    private function siteMap(): array
    {
        $path = storage_path('tm-sites.php');
        self::assertFileExists($path);
        self::assertStringStartsWith('<?php', (string) file_get_contents($path));

        return require $path;
    }
}
