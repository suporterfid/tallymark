<?php

declare(strict_types=1);

namespace App\Application\Aggregation;

use App\Domain\Aggregation\CardinalityGuard;
use App\Domain\Aggregation\SessionState;
use App\Domain\Aggregation\SessionStateMachine;
use App\Domain\Analytics\ReferrerNormalizer;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use App\Infrastructure\Persistence\Eloquent\SiteHost;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class AggregationRunner
{
    public function __construct(
        private readonly ReferrerNormalizer $referrers,
        private readonly Clock $clock,
        private readonly SessionStateMachine $sessionStateMachine,
    ) {}

    public function run(): void
    {
        while (($batch = IngestBatch::query()->where('status', 'staged')->orderBy('id')->first()) !== null) {
            DB::transaction(function () use ($batch): void {
                $batch = IngestBatch::query()->lockForUpdate()->findOrFail($batch->id);
                if ($batch->status !== 'staged') {
                    return;
                }
                $events = $batch->events()->orderBy('line_number')->get()->pluck('payload')->all();
                $this->aggregate($events);
                $batch->events()->delete();
                $batch->update(['status' => 'aggregated']);
            });
        }
    }

    /** @param list<array<string,mixed>> $events */
    private function aggregate(array $events): void
    {
        $sessionMetrics = $this->sessionMetrics($events);
        $groups = [];
        foreach ($events as $event) {
            if (($event['is_bot'] ?? false) || $event['event'] !== 'pageview') {
                continue;
            }
            $hour = (new \DateTimeImmutable((string) $event['timestamp']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
            $key = $event['site_id'].'|'.$hour;
            $groups[$key]['site_id'] = (int) $event['site_id'];
            $groups[$key]['hour'] = $hour;
            $groups[$key]['events'][] = $event;
        }
        foreach ($groups as $group) {
            $visitors = array_unique(array_column($group['events'], 'visitor_id'));
            $pageviews = count($group['events']);
            $metrics = $sessionMetrics[$group['site_id'].'|'.$group['hour']] ?? ['sessions' => 0, 'bounces' => 0, 'duration_sum' => 0];
            $this->add('stats_hourly_totals', ['site_id' => $group['site_id'], 'hour' => $group['hour']], ['pageviews' => $pageviews, 'visitors' => count($visitors), 'sessions' => $metrics['sessions'], 'bounces' => $metrics['bounces'], 'duration_sum' => $metrics['duration_sum']]);
            $paths = [];
            foreach ($group['events'] as $event) {
                $path = (string) (parse_url((string) $event['url'], PHP_URL_PATH) ?: '/');
                $paths[$path][] = $event;
            }
            foreach ($paths as $pathEvents) {
                $this->addPage($group['site_id'], $group['hour'], $pathEvents[0], count(array_unique(array_column($pathEvents, 'visitor_id'))), count($pathEvents));
            }
            $this->dimensions($group['site_id'], $group['hour'], $group['events']);
        }
        foreach ($sessionMetrics as $key => $metrics) {
            if (isset($groups[$key])) {
                continue;
            }
            [$siteId, $hour] = explode('|', $key, 2);
            $this->add('stats_hourly_totals', ['site_id' => (int) $siteId, 'hour' => $hour], ['pageviews' => 0, 'visitors' => 0, 'sessions' => $metrics['sessions'], 'bounces' => $metrics['bounces'], 'duration_sum' => $metrics['duration_sum']]);
        }
        $customGroups = [];
        foreach ($events as $event) {
            if (($event['is_bot'] ?? false) || $event['event'] === 'pageview') {
                continue;
            }
            $hour = (new \DateTimeImmutable((string) $event['timestamp']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
            $customGroups[$event['site_id'].'|'.$hour]['site_id'] = (int) $event['site_id'];
            $customGroups[$event['site_id'].'|'.$hour]['hour'] = $hour;
            $customGroups[$event['site_id'].'|'.$hour]['events'][] = $event;
        }
        foreach ($customGroups as $group) {
            $this->dimension($group['site_id'], $group['hour'], $group['events'], 'stats_hourly_events', fn ($e) => ['event_name' => (string) ($e['name'] ?: $e['event'])], 'count');
        }
        $this->aggregateGoals($events);
        $this->aggregateRealtime($events);
    }

    /** @param list<array<string,mixed>> $events */
    private function aggregateGoals(array $events): void
    {
        $groups = [];
        $siteIds = array_values(array_unique(array_map(static fn (array $event): int => (int) $event['site_id'], $events)));
        $goalsBySite = DB::table('goals')
            ->whereIn('site_id', $siteIds)
            ->where('is_enabled', true)
            ->get(['id', 'site_id', 'event_name', 'url_pattern'])
            ->groupBy('site_id');

        foreach ($events as $event) {
            if (($event['is_bot'] ?? false)) {
                continue;
            }

            $eventName = (string) (($event['name'] ?? '') ?: $event['event']);
            $hour = (new \DateTimeImmutable((string) $event['timestamp']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
            $path = (string) (parse_url((string) $event['url'], PHP_URL_PATH) ?: '/');
            foreach ($goalsBySite->get((int) $event['site_id'], []) as $goal) {
                $matchesEvent = $event['event'] !== 'pageview' && $goal->event_name === $eventName;
                $matchesUrl = $event['event'] === 'pageview'
                    && is_string($goal->url_pattern)
                    && str_starts_with($path, $goal->url_pattern);
                if (! $matchesEvent && ! $matchesUrl) {
                    continue;
                }

                $groups[$goal->id.'|'.$hour]['goal_id'] = (int) $goal->id;
                $groups[$goal->id.'|'.$hour]['site_id'] = (int) $event['site_id'];
                $groups[$goal->id.'|'.$hour]['hour'] = $hour;
                $groups[$goal->id.'|'.$hour]['events'][] = $event;
            }
        }

        foreach ($groups as $group) {
            $this->add('stats_hourly_goals', ['site_id' => $group['site_id'], 'goal_id' => $group['goal_id'], 'hour' => $group['hour']], ['conversions' => count($group['events']), 'visitors' => count(array_unique(array_column($group['events'], 'visitor_id')))]);
        }
    }

    /** @param list<array<string,mixed>> $events */
    private function aggregateRealtime(array $events): void
    {
        $groups = [];
        foreach ($events as $event) {
            if (($event['is_bot'] ?? false)) {
                continue;
            }

            $at = (new \DateTimeImmutable((string) $event['timestamp']))->setTimezone(new \DateTimeZone('UTC'));
            $bucket = $at->setTime((int) $at->format('H'), intdiv((int) $at->format('i'), 5) * 5)->format('Y-m-d H:i:00');
            $key = $event['site_id'].'|'.$bucket;
            $groups[$key]['site_id'] = (int) $event['site_id'];
            $groups[$key]['bucket'] = $bucket;
            $groups[$key]['events'][] = $event;
        }

        foreach ($groups as $group) {
            $pageviews = count(array_filter($group['events'], static fn (array $event): bool => $event['event'] === 'pageview'));
            $this->add('stats_realtime_five_minutes', ['site_id' => $group['site_id'], 'bucket' => $group['bucket']], ['pageviews' => $pageviews, 'events' => count($group['events']), 'visitors' => count(array_unique(array_column($group['events'], 'visitor_id')))]);
        }
    }

    /** @param list<array<string,mixed>> $events @return array<string,array{sessions:int,bounces:int,duration_sum:int}> */
    private function sessionMetrics(array $events): array
    {
        usort($events, static fn (array $a, array $b): int => strcmp((string) $a['timestamp'], (string) $b['timestamp']));
        $metrics = [];

        foreach ($events as $event) {
            if (($event['is_bot'] ?? false)) {
                continue;
            }

            $occurredAt = new \DateTimeImmutable((string) $event['timestamp']);
            $utc = $occurredAt->setTimezone(new \DateTimeZone('UTC'));
            $siteId = (int) $event['site_id'];
            $visitorId = (string) $event['visitor_id'];
            $day = $utc->format('Y-m-d');
            $stateRow = DB::table('session_states')
                ->where(['site_id' => $siteId, 'visitor_id' => $visitorId, 'day' => $day])
                ->lockForUpdate()
                ->first();
            $state = $stateRow === null ? null : new SessionState(
                (string) $stateRow->hour,
                new \DateTimeImmutable((string) $stateRow->last_event_at),
                $stateRow->last_pageview_at === null ? null : new \DateTimeImmutable((string) $stateRow->last_pageview_at),
                (int) $stateRow->pageviews,
            );
            $transition = $this->sessionStateMachine->transition($state, $occurredAt, (string) $event['event']);
            $this->persistSessionState($siteId, $visitorId, $day, $transition->state);

            $key = $siteId.'|'.$transition->state->hour;
            $metrics[$key] ??= ['sessions' => 0, 'bounces' => 0, 'duration_sum' => 0];
            $metrics[$key]['sessions'] += $transition->sessions;
            $metrics[$key]['bounces'] += $transition->bounces;
            $metrics[$key]['duration_sum'] += $transition->durationSum;
        }

        return $metrics;
    }

    private function persistSessionState(int $siteId, string $visitorId, string $day, SessionState $state): void
    {
        DB::table('session_states')->updateOrInsert(
            ['site_id' => $siteId, 'visitor_id' => $visitorId, 'day' => $day],
            [
                'hour' => $state->hour,
                'last_event_at' => $state->lastEventAt->format('Y-m-d H:i:s'),
                'last_pageview_at' => $state->lastPageviewAt?->format('Y-m-d H:i:s'),
                'pageviews' => $state->pageviews,
            ],
        );
    }

    /** @param list<array<string,mixed>> $events */
    private function dimensions(int $siteId, string $hour, array $events): void
    {
        $siteHosts = $this->siteHosts($siteId, $events);
        $this->dimension($siteId, $hour, $events, 'stats_hourly_referrers', fn ($e) => ['referrer' => $this->referrers->normalize((string) $e['url'], $e['referrer'] ?? null, $siteHosts)->source], 'pageviews');
        $this->dimension($siteId, $hour, $events, 'stats_hourly_countries', fn ($e) => ['country' => 'unknown'], 'pageviews');
        $this->dimension($siteId, $hour, $events, 'stats_hourly_devices', fn ($e) => ['device' => (string) ($e['device'] ?? 'unknown'), 'browser' => (string) ($e['browser'] ?? 'unknown'), 'os' => (string) ($e['os'] ?? 'unknown')], 'pageviews');
        $this->dimension($siteId, $hour, $events, 'stats_hourly_campaigns', function ($e) {
            parse_str((string) parse_url((string) $e['url'], PHP_URL_QUERY), $q);

            return ['source' => (string) ($q['utm_source'] ?? 'direct'), 'medium' => (string) ($q['utm_medium'] ?? 'none'), 'campaign' => (string) ($q['utm_campaign'] ?? '(none)')];
        }, 'pageviews');
    }

    /** @param list<array<string,mixed>> $events @return list<string> */
    private function siteHosts(int $siteId, array $events): array
    {
        $hosts = SiteHost::withoutGlobalScopes()->where('site_id', $siteId)->pluck('hostname')->all();

        foreach ($events as $event) {
            $host = parse_url((string) $event['url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /** @param list<array<string,mixed>> $events @param callable(array<string,mixed>):array<string,string> $keys */
    private function dimension(int $siteId, string $hour, array $events, string $table, callable $keys, string $metric): void
    {
        $groups = [];
        foreach ($events as $event) {
            $key = $keys($event);
            $groups[json_encode($key)][] = $event;
        }

        foreach ($groups as $encoded => $rows) {
            $key = json_decode($encoded, true);
            $key = $this->guardDimension($table, $siteId, $hour, $key);
            $this->add(
                $table,
                array_merge(['site_id' => $siteId, 'hour' => $hour], $key),
                [$metric => count($rows), 'visitors' => count(array_unique(array_column($rows, 'visitor_id')))],
            );
        }
    }

    /** @param array<string,string> $keys @return array<string,string> */
    private function guardDimension(string $table, int $siteId, string $hour, array $keys): array
    {
        $query = DB::table($table)->where(array_merge(['site_id' => $siteId, 'hour' => $hour], $keys));
        if ($query->exists() || DB::table($table)->where(['site_id' => $siteId, 'hour' => $hour])->count() < (int) (getenv('TM_MAX_DISTINCT_PER_BUCKET') ?: 200)) {
            return $keys;
        }

        foreach ($keys as $name => $_) {
            $keys[$name] = '(other)';
        }

        $this->recordCardinalityWarning($siteId);

        return $keys;
    }

    /** @param array<string,int|string> $keys @param array<string,int> $metrics */
    private function add(string $table, array $keys, array $metrics): void
    {
        $updates = [];
        foreach ($metrics as $key => $value) {
            $updates[$key] = DB::raw($key.' + '.(int) $value);
        }

        if (DB::table($table)->where($keys)->update($updates) === 0) {
            try {
                DB::table($table)->insert(array_merge($keys, $metrics));
            } catch (QueryException) {
                DB::table($table)->where($keys)->update($updates);
            }
        }
    }

    /** @param array<string,mixed> $event */
    private function addPage(int $siteId, string $hour, array $event, int $visitors, int $pageviews): void
    {
        $path = (string) (parse_url((string) $event['url'], PHP_URL_PATH) ?: '/');
        $existing = DB::table('stats_hourly_pages')->where(['site_id' => $siteId, 'hour' => $hour])->pluck('path')->all();
        $decision = (new CardinalityGuard((int) (getenv('TM_MAX_DISTINCT_PER_BUCKET') ?: 500)))->guard($path, $existing);
        if ($decision->folded) {
            $this->recordCardinalityWarning($siteId);
        }

        $this->add(
            'stats_hourly_pages',
            ['site_id' => $siteId, 'hour' => $hour, 'path' => $decision->value],
            ['pageviews' => $pageviews, 'visitors' => $visitors, 'bounces' => 0, 'duration_sum' => 0],
        );
    }

    private function recordCardinalityWarning(int $siteId): void
    {
        $now = $this->clock->now();

        DB::table('system_heartbeats')->updateOrInsert(
            ['name' => 'cardinality-cap:'.$siteId],
            [
                'status' => 'warning',
                'last_seen_at' => $now,
                'message' => 'Hourly dimension cap reached',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }
}
