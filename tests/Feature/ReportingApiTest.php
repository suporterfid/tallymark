<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ReportingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_bounded_preaggregated_metrics_and_labels_hourly_visitors_as_approximate(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Example',
            'timezone' => 'UTC',
            'site_key' => 'test-site-key',
        ]);
        DB::table('stats_hourly_totals')->insert([
            'site_id' => $site->id, 'hour' => '2026-08-04 12:00:00', 'pageviews' => 3,
            'visitors' => 2, 'sessions' => 2, 'bounces' => 1, 'duration_sum' => 120,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-04&to=2026-08-04');
        $queries = DB::getQueryLog();

        $response->assertOk()
            ->assertJsonPath('data.pageviews', 3)
            ->assertJsonPath('data.visitors', 2)
            ->assertJsonPath('meta.visitor_label', 'visits (approximate)');
        self::assertLessThanOrEqual(4, count($queries));
    }

    public function test_dashboard_returns_a_bounded_page_breakdown_from_hourly_aggregates(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'second-site-key']);
        DB::table('stats_hourly_pages')->insert([
            ['site_id' => $site->id, 'hour' => '2026-08-04 12:00:00', 'path' => '/pricing', 'pageviews' => 3, 'visitors' => 2, 'bounces' => 1, 'duration_sum' => 120],
            ['site_id' => $site->id, 'hour' => '2026-08-04 13:00:00', 'path' => '/pricing', 'pageviews' => 2, 'visitors' => 1, 'bounces' => 0, 'duration_sum' => 60],
        ]);

        $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-04&to=2026-08-04&dimension=pages')
            ->assertOk()
            ->assertJsonPath('data.breakdown.0.key', '/pricing')
            ->assertJsonPath('data.breakdown.0.pageviews', 5);
    }

    public function test_dashboard_returns_referrer_country_device_campaign_and_event_breakdowns_from_aggregates(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'third-site-key']);
        $hour = '2026-08-04 12:00:00';
        DB::table('stats_hourly_referrers')->insert(['site_id' => $site->id, 'hour' => $hour, 'referrer' => 'search', 'pageviews' => 4, 'visitors' => 3]);
        DB::table('stats_hourly_countries')->insert(['site_id' => $site->id, 'hour' => $hour, 'country' => 'BR', 'pageviews' => 4, 'visitors' => 3]);
        DB::table('stats_hourly_devices')->insert(['site_id' => $site->id, 'hour' => $hour, 'device' => 'mobile', 'browser' => 'chrome', 'os' => 'android', 'pageviews' => 4, 'visitors' => 3]);
        DB::table('stats_hourly_campaigns')->insert(['site_id' => $site->id, 'hour' => $hour, 'source' => 'search', 'medium' => 'cpc', 'campaign' => 'launch', 'pageviews' => 4, 'visitors' => 3]);
        DB::table('stats_hourly_events')->insert(['site_id' => $site->id, 'hour' => $hour, 'event_name' => 'signup', 'count' => 2, 'visitors' => 2]);

        foreach (['referrers' => 'search', 'countries' => 'BR', 'devices' => 'mobile / chrome / android', 'campaigns' => 'search / cpc / launch', 'events' => 'signup'] as $dimension => $key) {
            $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-04&to=2026-08-04&dimension='.$dimension)
                ->assertOk()
                ->assertJsonPath('data.breakdown.0.key', $key);
        }
    }

    /** @return array{Tenant, User} */
    private function tenantWithMember(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Example tenant', 'slug' => 'example-tenant']);
        $user = User::query()->create(['name' => 'Member', 'email' => 'member@example.test', 'password' => Hash::make('password')]);
        TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'tenant_admin']);

        return [$tenant, $user];
    }
}
