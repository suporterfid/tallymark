<?php

declare(strict_types=1);

namespace App\Application\Rollups;

use App\Domain\Shared\Clock;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class RollupService
{
    /** @var array<string,array{daily:string,dimensions:list<string>,metrics:list<string>}> */
    private const TABLES = [
        'stats_hourly_totals' => ['daily' => 'stats_daily_totals', 'dimensions' => [], 'metrics' => ['pageviews', 'visitors', 'sessions', 'bounces', 'duration_sum']],
        'stats_hourly_pages' => ['daily' => 'stats_daily_pages', 'dimensions' => ['path'], 'metrics' => ['pageviews', 'visitors', 'bounces', 'duration_sum']],
        'stats_hourly_referrers' => ['daily' => 'stats_daily_referrers', 'dimensions' => ['referrer'], 'metrics' => ['pageviews', 'visitors']],
        'stats_hourly_countries' => ['daily' => 'stats_daily_countries', 'dimensions' => ['country'], 'metrics' => ['pageviews', 'visitors']],
        'stats_hourly_devices' => ['daily' => 'stats_daily_devices', 'dimensions' => ['device', 'browser', 'os'], 'metrics' => ['pageviews', 'visitors']],
        'stats_hourly_campaigns' => ['daily' => 'stats_daily_campaigns', 'dimensions' => ['source', 'medium', 'campaign'], 'metrics' => ['pageviews', 'visitors']],
        'stats_hourly_events' => ['daily' => 'stats_daily_events', 'dimensions' => ['event_name'], 'metrics' => ['count', 'visitors']],
    ];

    public function __construct(private readonly Clock $clock) {}

    public function rollup(?string $day = null): void
    {
        $day ??= $this->yesterday()->format('Y-m-d');
        $this->assertClosedDay($day);

        DB::transaction(function () use ($day): void {
            foreach (self::TABLES as $hourly => $definition) {
                $this->rollupTable($hourly, $definition['daily'], $definition['dimensions'], $definition['metrics'], $day);
            }

            $this->rollupVisitors($day);
            $this->recordHeartbeat();
        });
    }

    /** @param list<string> $dimensions @param list<string> $metrics */
    private function rollupTable(string $hourly, string $daily, array $dimensions, array $metrics, string $day): void
    {
        $rows = DB::table($hourly)->whereDate('hour', $day)->get();
        DB::table($daily)->where('day', $day)->delete();

        $groups = [];
        foreach ($rows as $row) {
            $values = ['site_id' => (int) $row->site_id, 'day' => $day];
            foreach ($dimensions as $dimension) {
                $values[$dimension] = (string) $row->{$dimension};
            }
            $key = json_encode($values);
            $groups[$key] ??= $values;
            foreach ($metrics as $metric) {
                $groups[$key][$metric] = ($groups[$key][$metric] ?? 0) + (int) $row->{$metric};
            }
        }

        if ($groups !== []) {
            DB::table($daily)->insert(array_values($groups));
        }
    }

    private function rollupVisitors(string $day): void
    {
        $visitors = DB::table('session_states')->where('day', $day)->get(['site_id', 'visitor_id']);

        if (! $visitors->isEmpty()) {
            DB::table('daily_visitors')->where('day', $day)->delete();
            DB::table('daily_visitors')->insert($visitors->map(static fn (object $visitor): array => [
                'site_id' => (int) $visitor->site_id,
                'day' => $day,
                'visitor_id' => (string) $visitor->visitor_id,
            ])->all());
        }

        DB::table('session_states')->where('day', $day)->delete();
    }

    private function assertClosedDay(string $day): void
    {
        $closedThrough = $this->yesterday()->format('Y-m-d');
        if ($day > $closedThrough) {
            throw new \InvalidArgumentException('Only closed UTC days can be rolled up.');
        }
    }

    private function yesterday(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->sub(new DateInterval('P1D'))->setTime(0, 0);
    }

    private function recordHeartbeat(): void
    {
        $now = $this->clock->now();
        DB::table('system_heartbeats')->updateOrInsert(
            ['name' => 'analytics:rollup'],
            ['status' => 'healthy', 'last_seen_at' => $now, 'message' => null, 'updated_at' => $now, 'created_at' => $now],
        );
    }
}
