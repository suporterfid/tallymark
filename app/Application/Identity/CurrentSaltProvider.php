<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Salt;
use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;

final class CurrentSaltProvider
{
    public function __construct(private readonly Clock $clock) {}

    public function current(): Salt
    {
        $now = $this->now();
        $midnight = $now->setTime(0, 0);
        $activeOn = $midnight->format('Y-m-d');
        $current = Salt::query()->where('active_on', $activeOn)->first();
        $latest = Salt::query()->orderByDesc('active_on')->first();
        $stale = $current === null
            && $latest !== null
            && $latest->active_on->format('Y-m-d') < $activeOn;

        if ($current === null) {
            try {
                $current = Salt::query()->create([
                    'active_on' => $activeOn,
                    'value' => bin2hex(random_bytes(32)),
                    'destroy_at' => $midnight->add(new DateInterval('P1DT1H')),
                ]);
            } catch (QueryException $exception) {
                $current = Salt::query()->where('active_on', $activeOn)->first();

                if ($current === null) {
                    throw $exception;
                }
            }
        }

        Salt::query()->where('destroy_at', '<=', $now)->delete();
        $this->recordMaintenanceState($now, $stale);

        return $current;
    }

    private function recordMaintenanceState(DateTimeImmutable $now, bool $stale): void
    {
        $heartbeat = SystemHeartbeat::query()->firstOrNew(['name' => 'analytics:maintenance']);
        $heartbeat->last_seen_at = $now;

        if (! $heartbeat->exists) {
            $heartbeat->status = 'healthy';
        }

        if ($stale) {
            $heartbeat->status = 'alarm';
            $heartbeat->last_error_at = $now;
            $heartbeat->message = 'Salt rotation ran after the UTC midnight boundary.';
        }

        $heartbeat->save();
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
