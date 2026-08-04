<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Ingest\BufferReader;
use App\Application\Ingest\IngestClaimLease;
use App\Application\Ingest\IngestRunner;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class IngestPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $bufferDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bufferDirectory = storage_path('tm-buffer');
        if (! is_dir($this->bufferDirectory)) {
            mkdir($this->bufferDirectory, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->bufferDirectory.DIRECTORY_SEPARATOR.'*.ndjson') ?: [] as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_ingest_stages_closed_buffers_once_and_skips_malformed_lines(): void
    {
        self::assertTrue(class_exists(IngestRunner::class));

        $clock = new FixedClock(new DateTimeImmutable('2026-08-04 12:01:00 UTC'));
        $this->app->instance(Clock::class, $clock);
        $closed = $this->bufferDirectory.DIRECTORY_SEPARATOR.'202608041200-0.ndjson';
        $open = $this->bufferDirectory.DIRECTORY_SEPARATOR.'202608041201-0.ndjson';
        file_put_contents($closed, $this->eventLine()."\n{malformed}\n");
        file_put_contents($open, $this->eventLine()."\n");

        $this->artisan('analytics:ingest')->assertSuccessful();

        $this->assertDatabaseHas('ingest_batches', [
            'filename' => '202608041200-0.ndjson',
            'status' => 'staged',
            'accepted_lines' => 1,
            'malformed_lines' => 1,
        ]);
        $this->assertDatabaseCount('ingest_events', 1);
        $this->assertDatabaseHas('system_heartbeats', ['name' => 'analytics:ingest', 'status' => 'healthy', 'last_seen_at' => '2026-08-04 12:01:00']);
        self::assertFileDoesNotExist($closed);
        self::assertFileExists($open);

        $this->artisan('analytics:ingest')->assertSuccessful();

        $this->assertDatabaseCount('ingest_batches', 1);
        $this->assertDatabaseCount('ingest_events', 1);
    }

    public function test_a_second_ingest_run_does_not_process_a_buffer_with_an_active_claim_lease(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-04 12:01:00 UTC'));
        $this->app->instance(Clock::class, $clock);
        $path = $this->bufferDirectory.DIRECTORY_SEPARATOR.'202608041200-0.ndjson';
        file_put_contents($path, $this->eventLine()."\n".$this->eventLine()."\n");
        IngestBatch::query()->create([
            'filename' => '202608041200-0.ndjson',
            'status' => 'processing',
            'claim_token' => 'active-claim',
            'claim_expires_at' => new DateTimeImmutable('2026-08-04 12:11:00 UTC'),
        ]);

        $this->app->make(IngestRunner::class)->run();

        self::assertFileExists($path);
        $this->assertDatabaseCount('ingest_events', 0);
        $this->assertDatabaseHas('ingest_batches', ['filename' => '202608041200-0.ndjson', 'status' => 'processing']);
    }

    public function test_a_failed_ingest_run_records_an_alarm_heartbeat(): void
    {
        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2026-08-04 12:01:00 UTC')));
        $this->app->instance(BufferReader::class, new class extends BufferReader
        {
            public function closedFiles(DateTimeImmutable $now): array
            {
                throw new \RuntimeException('Buffer unavailable');
            }
        });

        try {
            $this->app->make(IngestRunner::class)->run();
            self::fail('Expected the ingest failure to be rethrown.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('system_heartbeats', ['name' => 'analytics:ingest', 'status' => 'alarm', 'last_seen_at' => '2026-08-04 12:01:00', 'message' => 'Ingest run failed.']);
        }
    }

    public function test_ingest_releases_a_partial_buffer_when_its_tick_budget_is_exhausted(): void
    {
        $clock = new class implements Clock
        {
            /** @var list<DateTimeImmutable> */
            private array $moments;

            public function __construct()
            {
                $this->moments = [
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:01 UTC'),
                ];
            }

            public function now(): DateTimeImmutable
            {
                return array_shift($this->moments) ?? new DateTimeImmutable('2026-08-04 12:01:01 UTC');
            }
        };
        $this->app->instance(Clock::class, $clock);
        $path = $this->bufferDirectory.DIRECTORY_SEPARATOR.'202608041200-0.ndjson';
        file_put_contents($path, $this->eventLine()."\n".$this->eventLine()."\n");
        putenv('TM_INGEST_TARGET_SECONDS=1');

        try {
            $this->app->make(IngestRunner::class)->run();
        } finally {
            putenv('TM_INGEST_TARGET_SECONDS');
        }

        self::assertFileExists($path);
        $this->assertDatabaseCount('ingest_events', 1);
        $this->assertDatabaseHas('ingest_batches', ['filename' => '202608041200-0.ndjson', 'status' => 'processing', 'next_line' => 1]);

        $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2026-08-04 12:01:01 UTC')));
        $this->app->make(IngestRunner::class)->run();

        self::assertFileDoesNotExist($path);
        $this->assertDatabaseCount('ingest_events', 2);
        $this->assertDatabaseHas('ingest_batches', ['filename' => '202608041200-0.ndjson', 'status' => 'staged', 'next_line' => 2]);
    }

    public function test_a_stale_worker_cannot_release_a_reclaimed_ingest_lease(): void
    {
        self::assertTrue(class_exists(IngestClaimLease::class));

        $batch = IngestBatch::query()->create([
            'filename' => '202608041200-0.ndjson',
            'status' => 'processing',
            'claim_token' => 'new-owner',
            'claim_expires_at' => new DateTimeImmutable('2026-08-04 12:11:00 UTC'),
        ]);

        self::assertFalse($this->app->make(IngestClaimLease::class)->release($batch->id, 'stale-owner'));
        $this->assertDatabaseHas('ingest_batches', [
            'id' => $batch->id,
            'claim_token' => 'new-owner',
            'status' => 'processing',
        ]);
    }

    public function test_a_resumed_buffer_checks_its_budget_while_skipping_checkpointed_lines(): void
    {
        $clock = new class implements Clock
        {
            /** @var list<DateTimeImmutable> */
            private array $moments;

            public function __construct()
            {
                $this->moments = [
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:01 UTC'),
                    new DateTimeImmutable('2026-08-04 12:01:00 UTC'),
                ];
            }

            public function now(): DateTimeImmutable
            {
                return array_shift($this->moments) ?? new DateTimeImmutable('2026-08-04 12:01:01 UTC');
            }
        };
        $this->app->instance(Clock::class, $clock);
        $path = $this->bufferDirectory.DIRECTORY_SEPARATOR.'202608041200-0.ndjson';
        file_put_contents($path, $this->eventLine()."\n");
        IngestBatch::query()->create([
            'filename' => '202608041200-0.ndjson',
            'status' => 'processing',
            'claim_token' => 'expired-owner',
            'claim_expires_at' => new DateTimeImmutable('2026-08-04 12:00:00 UTC'),
            'next_line' => 1,
        ]);
        putenv('TM_INGEST_TARGET_SECONDS=1');

        try {
            $this->app->make(IngestRunner::class)->run();
        } finally {
            putenv('TM_INGEST_TARGET_SECONDS');
        }

        self::assertFileExists($path);
        $this->assertDatabaseCount('ingest_events', 0);
        $this->assertDatabaseHas('ingest_batches', ['filename' => '202608041200-0.ndjson', 'status' => 'processing', 'next_line' => 1]);
    }

    private function eventLine(): string
    {
        return (string) json_encode([
            'site_id' => 7,
            'visitor_id' => '0123456789abcdef',
            'timestamp' => '2026-08-04T12:00:00+00:00',
            'url' => 'https://example.test/pricing',
            'referrer' => null,
            'event' => 'pageview',
            'name' => '',
            'properties' => ['plan' => 'pro'],
        ], JSON_THROW_ON_ERROR);
    }
}
