<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Aggregation\AggregationRunner;
use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use App\Infrastructure\Persistence\Eloquent\IngestEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_a_staged_batch_once_even_when_retried(): void
    {
        self::assertTrue(class_exists(AggregationRunner::class));
        $batch = $this->stagedBatch();
        $this->stageEvent($batch, 1, '/pricing', '2026-08-04T12:00:00+00:00');
        $this->stageEvent($batch, 2, '/pricing', '2026-08-04T12:01:00+00:00');

        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseHas('stats_hourly_totals', [
            'site_id' => 7,
            'hour' => '2026-08-04 12:00:00',
            'pageviews' => 2,
            'visitors' => 1,
            'sessions' => 1,
            'bounces' => 0,
            'duration_sum' => 60,
        ]);
        $this->assertDatabaseHas('stats_hourly_pages', [
            'site_id' => 7,
            'hour' => '2026-08-04 12:00:00',
            'path' => '/pricing',
            'pageviews' => 2,
            'visitors' => 1,
        ]);

        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseCount('stats_hourly_totals', 1);
        $this->assertDatabaseCount('stats_hourly_pages', 1);
        $this->assertDatabaseCount('ingest_events', 0);
        $this->assertDatabaseHas('ingest_batches', ['id' => $batch->id, 'status' => 'aggregated']);
    }

    public function test_it_folds_excess_path_values_and_records_a_warning(): void
    {
        putenv('TM_MAX_DISTINCT_PER_BUCKET=1');

        try {
            $batch = $this->stagedBatch();
            $this->stageEvent($batch, 1, '/pricing', '2026-08-04T12:00:00+00:00');
            $this->stageEvent($batch, 2, '/docs', '2026-08-04T12:01:00+00:00');

            $this->artisan('analytics:aggregate')->assertSuccessful();
        } finally {
            putenv('TM_MAX_DISTINCT_PER_BUCKET');
        }

        $this->assertDatabaseHas('stats_hourly_pages', ['site_id' => 7, 'path' => '(other)', 'pageviews' => 1]);
        $this->assertDatabaseHas('system_heartbeats', ['name' => 'cardinality-cap:7', 'status' => 'warning']);
    }

    public function test_it_populates_each_hourly_dimension_from_sanitized_event_fields(): void
    {
        $batch = $this->stagedBatch();
        $this->stageEvent($batch, 1, '/pricing?utm_source=search&utm_medium=cpc&utm_campaign=launch', '2026-08-04T12:00:00+00:00', 'https://news.example.org/story');
        $this->stageEvent($batch, 2, '/pricing', '2026-08-04T12:00:01+00:00', null, 'signup');

        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseHas('stats_hourly_referrers', ['site_id' => 7, 'referrer' => 'example.org', 'pageviews' => 1, 'visitors' => 1]);
        $this->assertDatabaseHas('stats_hourly_countries', ['site_id' => 7, 'country' => 'unknown', 'pageviews' => 1, 'visitors' => 1]);
        $this->assertDatabaseHas('stats_hourly_devices', ['site_id' => 7, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux', 'pageviews' => 1, 'visitors' => 1]);
        $this->assertDatabaseHas('stats_hourly_campaigns', ['site_id' => 7, 'source' => 'search', 'medium' => 'cpc', 'campaign' => 'launch', 'pageviews' => 1, 'visitors' => 1]);
        $this->assertDatabaseHas('stats_hourly_events', ['site_id' => 7, 'event_name' => 'signup', 'count' => 1, 'visitors' => 1]);
    }

    public function test_it_keeps_a_session_open_across_staged_batches(): void
    {
        $first = $this->stagedBatch();
        $this->stageEvent($first, 1, '/pricing', '2026-08-04T12:00:00+00:00');
        $this->artisan('analytics:aggregate')->assertSuccessful();

        $second = IngestBatch::query()->create(['filename' => '202608041201-0.ndjson', 'status' => 'staged', 'staged_at' => '2026-08-04 12:03:00']);
        $this->stageEvent($second, 1, '/pricing', '2026-08-04T12:05:00+00:00');
        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseHas('stats_hourly_totals', ['site_id' => 7, 'hour' => '2026-08-04 12:00:00', 'sessions' => 1, 'bounces' => 0, 'duration_sum' => 300]);
    }

    private function stagedBatch(): IngestBatch
    {
        return IngestBatch::query()->create([
            'filename' => '202608041200-0.ndjson',
            'status' => 'staged',
            'staged_at' => '2026-08-04 12:02:00',
        ]);
    }

    private function stageEvent(IngestBatch $batch, int $lineNumber, string $path, string $timestamp, ?string $referrer = null, string $event = 'pageview'): void
    {
        IngestEvent::query()->create([
            'ingest_batch_id' => $batch->id,
            'line_number' => $lineNumber,
            'payload' => [
                'site_id' => 7,
                'visitor_id' => '0123456789abcdef',
                'timestamp' => $timestamp,
                'url' => 'https://example.test'.$path,
                'referrer' => $referrer,
                'event' => $event,
                'name' => $event === 'pageview' ? '' : $event,
                'properties' => [],
                'is_bot' => false,
                'device' => 'desktop',
                'browser' => 'chrome',
                'os' => 'linux',
            ],
        ]);
    }
}
