<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\TaskConnect\TaskConnectDigestDelegator;
use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use App\Infrastructure\Persistence\Eloquent\IngestEvent;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TaskConnectIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_taskconnect_never_leaves_the_aggregation_process(): void
    {
        Http::fake();
        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'disabled-taskconnect-site-key']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'name' => 'Signup', 'event_name' => 'signup', 'is_enabled' => true]);
        $batch = IngestBatch::query()->create(['filename' => '202608041230-0.ndjson', 'status' => 'staged', 'staged_at' => '2026-08-04 12:31:00']);
        IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => 1, 'payload' => ['site_id' => $site->id, 'visitor_id' => 'visitor-secret', 'timestamp' => '2026-08-04T12:28:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => 'signup', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);

        $this->artisan('analytics:aggregate')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('taskconnect_submissions', ['site_id' => $site->id, 'goal_id' => $goalId]);
    }

    public function test_aggregation_submits_one_sanitized_goal_task_per_goal_hour(): void
    {
        config()->set('taskconnect.enabled', true);
        config()->set('taskconnect.base_url', 'https://tasks.example.test');
        config()->set('taskconnect.api_key', 'tc_live_example');
        config()->set('taskconnect.tenant_id', 'ten_taskconnect');
        config()->set('taskconnect.environment_id', 'env_analytics');
        config()->set('taskconnect.goal_conversion_url', 'https://automation.example.test/conversions');

        Http::fake([
            'https://tasks.example.test/*' => Http::response(['data' => ['id' => 'task_conversion_1']], 201),
        ]);

        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'taskconnect-site-key']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'public_id' => 'goal_signup', 'name' => 'Signup', 'event_name' => 'signup', 'is_enabled' => true]);
        $batch = IngestBatch::query()->create(['filename' => '202608041230-0.ndjson', 'status' => 'staged', 'staged_at' => '2026-08-04 12:31:00']);
        foreach (['visitor-secret-a', 'visitor-secret-b', 'visitor-secret-a'] as $line => $visitorId) {
            IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => $line + 1, 'payload' => ['site_id' => $site->id, 'visitor_id' => $visitorId, 'timestamp' => '2026-08-04T12:28:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => 'signup', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);
        }

        $this->artisan('analytics:aggregate')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($site): bool {
            $body = $request->data();
            $taskPayload = json_decode((string) ($body['body'] ?? ''), true);

            return $request->url() === 'https://tasks.example.test/v1/tenants/ten_taskconnect/environments/env_analytics/tasks'
                && $request->hasHeader('Authorization', 'Bearer tc_live_example')
                && $request->hasHeader('Idempotency-Key')
                && $body['name'] === 'TallyMark goal Signup 2026-08-04T12:00:00+00:00'
                && $body['method'] === 'POST'
                && $body['url_or_path'] === 'https://automation.example.test/conversions'
                && $body['definition_status'] === 'active'
                && $body['content_type'] === 'application/json'
                && ($taskPayload['type'] ?? null) === 'goal_conversion'
                && ($taskPayload['site_id'] ?? null) === $site->public_id
                && ($taskPayload['goal_id'] ?? null) === 'goal_signup'
                && ($taskPayload['period'] ?? null) === '2026-08-04T12:00:00+00:00'
                && ($taskPayload['count'] ?? null) === 3
                && ! str_contains(json_encode($body, JSON_THROW_ON_ERROR), 'visitor');
        });

        $this->assertDatabaseHas('taskconnect_submissions', [
            'goal_id' => $goalId,
            'site_id' => $site->id,
            'period' => '2026-08-04 12:00:00',
            'status' => 'accepted',
            'task_id' => 'task_conversion_1',
        ]);
    }

    public function test_batches_for_the_same_goal_hour_submit_one_aggregate_count_per_tick(): void
    {
        config()->set('taskconnect.enabled', true);
        config()->set('taskconnect.base_url', 'https://tasks.example.test');
        config()->set('taskconnect.api_key', 'tc_live_example');
        config()->set('taskconnect.tenant_id', 'ten_taskconnect');
        config()->set('taskconnect.environment_id', 'env_analytics');
        config()->set('taskconnect.goal_conversion_url', 'https://automation.example.test/conversions');
        Http::fake(['https://tasks.example.test/*' => Http::response(['data' => ['id' => 'task_conversion']], 201)]);

        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'later-batch-site-key']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'public_id' => 'goal_later_batch', 'name' => 'Signup', 'event_name' => 'signup', 'is_enabled' => true]);
        foreach (['202608041230-0.ndjson', '202608041231-0.ndjson'] as $line => $filename) {
            $batch = IngestBatch::query()->create(['filename' => $filename, 'status' => 'staged', 'staged_at' => '2026-08-04 12:31:00']);
            IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => 1, 'payload' => ['site_id' => $site->id, 'visitor_id' => 'visitor-'.$line, 'timestamp' => '2026-08-04T12:28:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => 'signup', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);
        }

        $this->artisan('analytics:aggregate')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('stats_hourly_goals', ['site_id' => $site->id, 'goal_id' => $goalId, 'conversions' => 2]);
        self::assertSame(1, DB::table('taskconnect_submissions')->where('goal_id', $goalId)->count());
        Http::assertSent(fn (Request $request): bool => json_decode((string) $request->data()['body'], true)['count'] === 2);
    }

    public function test_taskconnect_failure_is_recorded_and_retried_without_rolling_back_the_aggregate(): void
    {
        config()->set('taskconnect.enabled', true);
        config()->set('taskconnect.base_url', 'https://tasks.example.test');
        config()->set('taskconnect.api_key', 'tc_live_example');
        config()->set('taskconnect.tenant_id', 'ten_taskconnect');
        config()->set('taskconnect.environment_id', 'env_analytics');
        config()->set('taskconnect.goal_conversion_url', 'https://automation.example.test/conversions');
        Http::fake(['https://tasks.example.test/*' => Http::sequence()
            ->push([], 503)
            ->push(['data' => ['id' => 'task_retry_1']], 201)]);

        $tenant = Tenant::query()->create(['name' => 'Example', 'slug' => 'example']);
        $site = Site::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Example', 'timezone' => 'UTC', 'site_key' => 'retry-taskconnect-site-key']);
        $goalId = DB::table('goals')->insertGetId(['site_id' => $site->id, 'public_id' => 'goal_retry', 'name' => 'Retry', 'event_name' => 'signup', 'is_enabled' => true]);
        $batch = IngestBatch::query()->create(['filename' => '202608041230-0.ndjson', 'status' => 'staged', 'staged_at' => '2026-08-04 12:31:00']);
        IngestEvent::query()->create(['ingest_batch_id' => $batch->id, 'line_number' => 1, 'payload' => ['site_id' => $site->id, 'visitor_id' => 'visitor-secret', 'timestamp' => '2026-08-04T12:28:00+00:00', 'url' => 'https://example.test/pricing', 'referrer' => null, 'event' => 'signup', 'name' => 'signup', 'properties' => [], 'is_bot' => false, 'device' => 'desktop', 'browser' => 'chrome', 'os' => 'linux']]);

        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseHas('stats_hourly_goals', ['site_id' => $site->id, 'goal_id' => $goalId, 'conversions' => 1]);
        $this->assertDatabaseHas('taskconnect_submissions', ['goal_id' => $goalId, 'status' => 'failed', 'attempts' => 1]);

        DB::table('taskconnect_submissions')->where('goal_id', $goalId)->update(['next_attempt_at' => '2026-08-04 12:29:00']);

        $this->artisan('analytics:aggregate')->assertSuccessful();

        $this->assertDatabaseHas('taskconnect_submissions', ['goal_id' => $goalId, 'status' => 'accepted', 'task_id' => 'task_retry_1']);
    }

    public function test_digest_delegation_uses_a_stable_report_period_idempotency_key_and_broker_token(): void
    {
        config()->set('taskconnect.enabled', true);
        config()->set('taskconnect.base_url', 'https://tasks.example.test');
        config()->set('taskconnect.tenant_id', 'ten_taskconnect');
        config()->set('taskconnect.environment_id', 'env_analytics');
        config()->set('taskconnect.run_url_template', 'https://tasks.example.test/tasks/{task_id}');
        config()->set('grandpasson.outbound_enabled', true);
        config()->set('grandpasson.base_url', 'https://auth.example.test');
        config()->set('grandpasson.outbound_client_id', 'tallymark-taskconnect');
        config()->set('grandpasson.outbound_client_secret', 'taskconnect-secret');

        Http::fake([
            'https://auth.example.test/oauth/token' => Http::response(['access_token' => 'gpat_live_taskconnect', 'token_type' => 'Bearer'], 200),
            'https://tasks.example.test/*' => Http::response(['data' => ['id' => 'task_digest_1']], 201),
        ]);

        $delegator = app(TaskConnectDigestDelegator::class);
        $accepted = $delegator->delegate('report_42', '2026-08-04', 'https://mail.example.test/digest', ['site_id' => 'site_public', 'period' => '2026-08-04']);

        self::assertSame('task_digest_1', $accepted?->id);
        self::assertSame('https://tasks.example.test/tasks/task_digest_1', $accepted?->url);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://auth.example.test/oauth/token'
                && $request->data() === [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'tallymark-taskconnect',
                    'client_secret' => 'taskconnect-secret',
                    'scope' => 'tasks:write',
                ];
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://tasks.example.test/v1/tenants/ten_taskconnect/environments/env_analytics/tasks'
                && $request->hasHeader('Authorization', 'Bearer gpat_live_taskconnect')
                && $request->hasHeader('Idempotency-Key', 'tm-digest-3b3f23827a32bff8f420fb566eb495726cbce21e893063e574cd5ec0e19d62b0')
                && $request->data()['body'] === '{"site_id":"site_public","period":"2026-08-04"}';
        });
    }
}
