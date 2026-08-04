<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FixedClock;
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
        DB::table('stats_hourly_totals')->insert([
            'site_id' => $site->id, 'hour' => '2026-08-03 12:00:00', 'pageviews' => 2,
            'visitors' => 1, 'sessions' => 1, 'bounces' => 1, 'duration_sum' => 30,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-04&to=2026-08-04');
        $queries = DB::getQueryLog();

        $response->assertOk()
            ->assertJsonPath('data.pageviews', 3)
            ->assertJsonPath('data.visitors', 2)
            ->assertJsonPath('data.views_per_session', 1.5)
            ->assertJsonPath('data.bounce_rate', 50)
            ->assertJsonPath('data.average_session_duration', 60)
            ->assertJsonPath('comparison.pageviews', 2)
            ->assertJsonPath('comparison.sessions', 1)
            ->assertJsonPath('meta.visitor_label', 'visits (approximate)');
        self::assertLessThanOrEqual(7, count($queries));
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

    public function test_dashboard_uses_exact_daily_visitors_for_a_closed_full_day(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'daily-site-key']);
        DB::table('stats_daily_totals')->insert([
            ['site_id' => $site->id, 'day' => '2026-08-03', 'pageviews' => 7, 'visitors' => 2, 'sessions' => 3, 'bounces' => 1, 'duration_sum' => 300],
            ['site_id' => $site->id, 'day' => '2026-08-02', 'pageviews' => 5, 'visitors' => 4, 'sessions' => 4, 'bounces' => 1, 'duration_sum' => 240],
        ]);

        $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-03&to=2026-08-03')
            ->assertOk()
            ->assertJsonPath('data.pageviews', 7)
            ->assertJsonPath('data.visitors', 2)
            ->assertJsonPath('comparison.visitors', 4)
            ->assertJsonPath('meta.visitor_label', 'visitors')
            ->assertJsonPath('meta.comparison_visitor_label', 'visitors')
            ->assertJsonPath('meta.timezone', 'UTC');
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

    public function test_dashboard_returns_goal_conversions_and_thirty_minute_activity(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'goal-report-key']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'name' => 'Signup', 'event_name' => 'signup']);
        DB::table('stats_hourly_goals')->insert(['site_id' => $site->id, 'goal_id' => $goalId, 'hour' => '2026-08-04 12:00:00', 'conversions' => 2, 'visitors' => 2]);
        DB::table('stats_hourly_totals')->insert(['site_id' => $site->id, 'hour' => '2026-08-04 12:00:00', 'pageviews' => 4, 'visitors' => 2, 'sessions' => 4, 'bounces' => 1, 'duration_sum' => 120]);
        DB::table('stats_realtime_five_minutes')->insert([
            ['site_id' => $site->id, 'bucket' => '2026-08-04 12:00:00', 'pageviews' => 9, 'events' => 9, 'visitors' => 9],
            ['site_id' => $site->id, 'bucket' => '2026-08-04 12:25:00', 'pageviews' => 3, 'events' => 4, 'visitors' => 2],
        ]);

        $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-04&to=2026-08-04&dimension=goals')
            ->assertOk()->assertJsonPath('data.breakdown.0.key', 'Signup')->assertJsonPath('data.breakdown.0.conversions', 2)->assertJsonPath('data.breakdown.0.conversion_rate', 50);
        $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/realtime?until=2026-08-04T09:30:00-03:00')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.events', 9)->assertJsonPath('data.1.events', 4)->assertJsonPath('meta.window_minutes', 30)->assertJsonPath('meta.bucket_window_minutes', 35);
    }

    public function test_dashboard_uses_site_timezone_for_hourly_date_boundaries(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'America/Sao_Paulo', 'site_key' => 'timezone-site-key']);
        DB::table('stats_hourly_totals')->insert(['site_id' => $site->id, 'hour' => '2026-08-04 01:00:00', 'pageviews' => 3, 'visitors' => 2, 'sessions' => 2, 'bounces' => 1, 'duration_sum' => 120]);

        $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-03&to=2026-08-03')
            ->assertOk()->assertJsonPath('data.pageviews', 3)->assertJsonPath('meta.visitor_label', 'visits (approximate)');
    }

    public function test_dashboard_surfaces_ingest_freshness_and_data_loss_warnings(): void
    {
        $now = new DateTimeImmutable('2026-08-04 12:00:00 UTC');
        $this->app->instance(Clock::class, new FixedClock($now));
        [$tenant, $user] = $this->tenantWithMember();
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'health-site-key']);
        DB::table('system_heartbeats')->insert([
            ['name' => 'analytics:ingest', 'status' => 'healthy', 'last_seen_at' => $now, 'message' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'cardinality-cap:'.$site->id, 'status' => 'warning', 'last_seen_at' => $now, 'message' => 'Hourly dimension cap reached', 'created_at' => $now, 'updated_at' => $now],
        ]);
        file_put_contents(storage_path('tm-buffer/tm-shed.count'), "1\n1\n");

        try {
            $this->actingAs($user)->getJson('/api/v1/tenants/'.$tenant->public_id.'/sites/'.$site->public_id.'/report?from=2026-08-04&to=2026-08-04')
                ->assertOk()
                ->assertJsonPath('meta.operational.ingest.fresh', true)
                ->assertJsonPath('meta.operational.cardinality_warning', true)
                ->assertJsonPath('meta.operational.shed_events', 2);
        } finally {
            @unlink(storage_path('tm-buffer/tm-shed.count'));
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
