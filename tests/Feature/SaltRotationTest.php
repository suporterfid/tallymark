<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Identity\SaltRotationService;
use App\Domain\Identity\VisitorHasher;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Salt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Tests\Support\FixedClock;
use Tests\TestCase;
use DateTimeImmutable;

final class SaltRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_at_utc_midnight_changes_the_hash_and_destroys_the_expired_salt(): void
    {
        self::assertTrue(class_exists(SaltRotationService::class));

        $clock = new FixedClock(new DateTimeImmutable('2026-08-03 23:59:00 UTC'));
        $this->app->instance(Clock::class, $clock);
        $rotation = $this->app->make(SaltRotationService::class);
        $hasher = new VisitorHasher();

        $oldSalt = $rotation->maintain();
        $oldVisitorId = $hasher->hash($oldSalt->value, 7, '203.0.113.42', 'ExampleBrowser/1.0');
        self::assertSame($oldSalt->value, $this->siteMap()['salt']);
        self::assertSame(64, strlen($oldSalt->value));

        $clock->set(new DateTimeImmutable('2026-08-04 00:00:00 UTC'));
        $currentSalt = $rotation->maintain();

        self::assertNotSame($oldSalt->value, $currentSalt->value);
        self::assertNotSame($oldVisitorId, $hasher->hash($currentSalt->value, 7, '203.0.113.42', 'ExampleBrowser/1.0'));
        self::assertSame($currentSalt->value, $this->siteMap()['salt']);
        $this->assertDatabaseHas('salts', ['id' => $oldSalt->id]);

        $clock->set(new DateTimeImmutable('2026-08-04 01:00:00 UTC'));
        $rotation->maintain();

        $this->assertDatabaseMissing('salts', ['id' => $oldSalt->id]);
        $this->assertDatabaseMissing('salts', ['value' => '203.0.113.42']);
    }

    public function test_a_missed_midnight_rotation_raises_a_durable_operator_alarm(): void
    {
        self::assertTrue(class_exists(SaltRotationService::class));

        $clock = new FixedClock(new DateTimeImmutable('2026-08-03 12:00:00 UTC'));
        $this->app->instance(Clock::class, $clock);
        $rotation = $this->app->make(SaltRotationService::class);
        $rotation->maintain();

        $clock->set(new DateTimeImmutable('2026-08-04 00:10:00 UTC'));
        $rotation->maintain();

        $this->assertDatabaseHas('system_heartbeats', [
            'name' => 'analytics:maintenance',
            'status' => 'alarm',
            'message' => 'Salt rotation ran after the UTC midnight boundary.',
        ]);
    }

    public function test_maintenance_command_publishes_a_salt_without_creating_identity_storage_columns(): void
    {
        $this->artisan('analytics:maintenance')->assertSuccessful();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $this->siteMap()['salt']);

        self::assertSame('file', config('session.driver'));

        foreach (['salts', 'system_heartbeats', 'sessions'] as $table) {
            $columns = Schema::getColumnListing($table);
            self::assertNotContains('ip', $columns);
            self::assertNotContains('client_ip', $columns);
            self::assertNotContains('user_agent', $columns);
        }
    }

    public function test_rotation_reloads_the_salt_when_another_maintenance_run_wins_the_midnight_insert(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-04 00:00:00 UTC'));
        $this->app->instance(Clock::class, $clock);
        $rotation = $this->app->make(SaltRotationService::class);
        $insertedByOtherRun = false;

        Salt::creating(static function (Salt $salt) use (&$insertedByOtherRun): void {
            if ($insertedByOtherRun) {
                return;
            }

            $insertedByOtherRun = true;
            Salt::withoutEvents(static function () use ($salt): void {
                Salt::query()->create([
                    'active_on' => $salt->active_on,
                    'value' => str_repeat('c', 64),
                    'destroy_at' => $salt->destroy_at,
                ]);
            });
        });

        try {
            $salt = $rotation->maintain();
        } catch (QueryException) {
            self::fail('A concurrent active-salt insert must be reloaded instead of failing maintenance.');
        } finally {
            Salt::flushEventListeners();
        }

        self::assertSame(str_repeat('c', 64), $salt->value);
        self::assertSame(1, Salt::query()->count());
        self::assertSame($salt->value, $this->siteMap()['salt']);
    }

    public function test_the_session_privacy_migration_never_reintroduces_identity_columns_on_rollback(): void
    {
        $migration = (string) file_get_contents(base_path('database/migrations/2026_08_03_191000_remove_session_identity_columns.php'));
        $down = substr($migration, strpos($migration, 'public function down(): void'));

        self::assertStringNotContainsString('ip_address', $down);
        self::assertStringNotContainsString('user_agent', $down);
    }

    /** @return array{salt: string, sites: array<string, mixed>} */
    private function siteMap(): array
    {
        $path = storage_path('tm-sites.php');
        self::assertFileExists($path);

        return require $path;
    }
}
