<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\Clock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class RollupRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollup_materializes_exact_daily_visitors_and_is_idempotent(): void
    {
        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2026-08-04 00:10:00 UTC')));
        $this->hourlyTotals('2026-08-03 10:00:00', 3, 2, 2, 1, 120);
        $this->hourlyTotals('2026-08-03 11:00:00', 4, 2, 1, 0, 180);
        DB::table('session_states')->insert([
            ['site_id' => 7, 'visitor_id' => '0123456789abcdef', 'day' => '2026-08-03', 'hour' => '2026-08-03 10:00:00', 'last_event_at' => '2026-08-03 10:02:00', 'last_pageview_at' => '2026-08-03 10:02:00', 'pageviews' => 2],
            ['site_id' => 7, 'visitor_id' => 'fedcba9876543210', 'day' => '2026-08-03', 'hour' => '2026-08-03 11:00:00', 'last_event_at' => '2026-08-03 11:03:00', 'last_pageview_at' => '2026-08-03 11:03:00', 'pageviews' => 1],
        ]);

        $this->artisan('analytics:rollup', ['--day' => '2026-08-03'])->assertSuccessful();
        $this->artisan('analytics:rollup', ['--day' => '2026-08-03'])->assertSuccessful();

        $this->assertDatabaseHas('stats_daily_totals', [
            'site_id' => 7,
            'day' => '2026-08-03',
            'pageviews' => 7,
            'visitors' => 4,
            'sessions' => 3,
            'bounces' => 1,
            'duration_sum' => 300,
        ]);
        $this->assertDatabaseCount('stats_daily_totals', 1);
        $this->assertDatabaseCount('daily_visitors', 2);
    }

    public function test_maintenance_keeps_hourly_rows_until_their_daily_rollup_exists(): void
    {
        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2026-08-04 00:10:00 UTC')));
        putenv('RETENTION_STATS_HOURLY_DAYS=1');

        try {
            $this->hourlyTotals('2026-08-01 10:00:00', 3, 2, 2, 1, 120);

            $this->artisan('analytics:maintenance')->assertSuccessful();

            $this->assertDatabaseHas('stats_hourly_totals', ['site_id' => 7, 'hour' => '2026-08-01 10:00:00']);
            DB::table('stats_daily_totals')->insert(['site_id' => 7, 'day' => '2026-08-01', 'pageviews' => 3, 'visitors' => 2, 'sessions' => 2, 'bounces' => 1, 'duration_sum' => 120]);

            $this->artisan('analytics:maintenance')->assertSuccessful();
        } finally {
            putenv('RETENTION_STATS_HOURLY_DAYS');
        }

        $this->assertDatabaseMissing('stats_hourly_totals', ['site_id' => 7, 'hour' => '2026-08-01 10:00:00']);
    }

    private function hourlyTotals(string $hour, int $pageviews, int $visitors, int $sessions, int $bounces, int $durationSum): void
    {
        DB::table('stats_hourly_totals')->insert([
            'site_id' => 7,
            'hour' => $hour,
            'pageviews' => $pageviews,
            'visitors' => $visitors,
            'sessions' => $sessions,
            'bounces' => $bounces,
            'duration_sum' => $durationSum,
        ]);
    }
}
