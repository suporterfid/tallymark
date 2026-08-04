<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use App\Infrastructure\Persistence\Eloquent\IngestEvent;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GoalsRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregation_records_event_goal_conversions_and_five_minute_activity(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'goal-site-key']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'name' => 'Signup', 'event_name' => 'signup']);
        $batch = IngestBatch::query()->create(['filename' => '202608041230-0.ndjson', 'status' => 'staged', 'staged_at' => '2026-08-04 12:31:00']);
        IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => 1, 'payload' => ['site_id' => $site->id, 'visitor_id' => '0123456789abcdef', 'timestamp' => '2026-08-04T12:28:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => 'signup', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);
        IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => 2, 'payload' => ['site_id' => $site->id, 'visitor_id' => '0123456789abcdef', 'timestamp' => '2026-08-04T12:29:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => 'signup', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);
        IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => 3, 'payload' => ['site_id' => $site->id, 'visitor_id' => '0123456789abcdef', 'timestamp' => '2026-08-04T12:29:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => '', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);

        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseHas('stats_hourly_goals', ['site_id' => $site->id, 'goal_id' => $goalId, 'hour' => '2026-08-04 12:00:00', 'conversions' => 3, 'visitors' => 1]);
        $this->assertDatabaseHas('stats_realtime_five_minutes', ['site_id' => $site->id, 'bucket' => '2026-08-04 12:25:00', 'events' => 3]);
    }
}
