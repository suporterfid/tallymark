<?php

declare(strict_types=1);

namespace App\Application\Rollups;

use App\Domain\Shared\Clock;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class RetentionService
{
    private const CHUNK_SIZE = 1000;

    /** @var array<string,string> */
    private const HOURLY_TABLES = [
        'stats_hourly_totals' => 'stats_daily_totals',
        'stats_hourly_pages' => 'stats_daily_pages',
        'stats_hourly_referrers' => 'stats_daily_referrers',
        'stats_hourly_countries' => 'stats_daily_countries',
        'stats_hourly_devices' => 'stats_daily_devices',
        'stats_hourly_campaigns' => 'stats_daily_campaigns',
        'stats_hourly_events' => 'stats_daily_events',
        'stats_hourly_goals' => 'stats_daily_goals',
    ];

    public function __construct(private readonly Clock $clock) {}

    public function prune(): void
    {
        $this->pruneHourly();
        $this->deleteByDay('daily_visitors', 'day', $this->cutoff('RETENTION_DAILY_VISITORS_DAYS', 60));

        foreach (array_values(self::HOURLY_TABLES) as $daily) {
            $this->deleteByDay($daily, 'day', $this->cutoff('RETENTION_STATS_DAILY_DAYS', 1825));
        }
        DB::table('stats_realtime_five_minutes')
            ->where('bucket', '<', $this->now()->sub(new DateInterval('PT48H'))->format('Y-m-d H:i:00'))
            ->limit(self::CHUNK_SIZE)
            ->delete();
    }

    private function pruneHourly(): void
    {
        $cutoff = $this->cutoff('RETENTION_STATS_HOURLY_DAYS', 180);

        foreach (self::HOURLY_TABLES as $hourly => $daily) {
            $rows = DB::table($hourly)->where('hour', '<', $cutoff.' 00:00:00')->limit(self::CHUNK_SIZE)->get(['site_id', 'hour']);

            foreach ($rows as $row) {
                $day = substr((string) $row->hour, 0, 10);
                $rolledUp = DB::table($daily)->where(['site_id' => $row->site_id, 'day' => $day])->exists();
                if ($rolledUp) {
                    DB::table($hourly)->where(['site_id' => $row->site_id, 'hour' => $row->hour])->delete();
                }
            }
        }
    }

    private function deleteByDay(string $table, string $column, string $cutoff): void
    {
        DB::table($table)->where($column, '<', $cutoff)->limit(self::CHUNK_SIZE)->delete();
    }

    private function cutoff(string $environment, int $defaultDays): string
    {
        $days = (int) (getenv($environment) ?: $defaultDays);

        return $this->now()->sub(new DateInterval('P'.$days.'D'))->format('Y-m-d');
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
