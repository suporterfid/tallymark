<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Clock;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReportController extends Controller
{
    /** @var array<string,array{table:string,key:string,metric:string}> */
    private const DIMENSIONS = [
        'pages' => ['table' => 'stats_hourly_pages', 'key' => 'path', 'metric' => 'pageviews'],
        'referrers' => ['table' => 'stats_hourly_referrers', 'key' => 'referrer', 'metric' => 'pageviews'],
        'countries' => ['table' => 'stats_hourly_countries', 'key' => 'country', 'metric' => 'pageviews'],
        'devices' => ['table' => 'stats_hourly_devices', 'key' => "concat(device, ' / ', browser, ' / ', os)", 'metric' => 'pageviews'],
        'campaigns' => ['table' => 'stats_hourly_campaigns', 'key' => "concat(source, ' / ', medium, ' / ', campaign)", 'metric' => 'pageviews'],
        'events' => ['table' => 'stats_hourly_events', 'key' => 'event_name', 'metric' => 'count'],
    ];

    public function __construct(private readonly Clock $clock) {}

    public function show(Request $request, Tenant $tenant, string $siteId): JsonResponse
    {
        $range = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $site = Site::withoutGlobalScopes()->where(['tenant_id' => $tenant->id, 'public_id' => $siteId])->firstOrFail();
        $timezone = new \DateTimeZone($site->timezone);
        $from = new \DateTimeImmutable($range['from'].' 00:00:00', $timezone);
        $to = new \DateTimeImmutable($range['to'].' 23:59:59', $timezone);
        $hourFrom = $from->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $hourTo = $to->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $daily = $range['from'] === $range['to'] && $timezone->getName() === 'UTC'
            ? DB::table('stats_daily_totals')->where(['site_id' => $site->id, 'day' => $range['from']])->first()
            : null;
        $metrics = $daily ?? DB::table('stats_hourly_totals')
            ->where('site_id', $site->id)
            ->whereBetween('hour', [$hourFrom, $hourTo])
            ->selectRaw('coalesce(sum(pageviews), 0) as pageviews, coalesce(sum(visitors), 0) as visitors, coalesce(sum(sessions), 0) as sessions, coalesce(sum(bounces), 0) as bounces, coalesce(sum(duration_sum), 0) as duration_sum')
            ->first();
        $days = $from->diff($to)->days + 1;
        $comparisonFrom = $from->sub(new \DateInterval('P'.$days.'D'));
        $comparisonTo = $from->sub(new \DateInterval('P1D'))->setTime(23, 59, 59);
        $comparisonDaily = $daily !== null
            ? DB::table('stats_daily_totals')->where(['site_id' => $site->id, 'day' => $comparisonFrom->format('Y-m-d')])->first()
            : null;
        $comparison = $comparisonDaily ?? DB::table('stats_hourly_totals')
            ->where('site_id', $site->id)
            ->whereBetween('hour', [$comparisonFrom->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $comparisonTo->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')])
            ->selectRaw('coalesce(sum(pageviews), 0) as pageviews, coalesce(sum(visitors), 0) as visitors, coalesce(sum(sessions), 0) as sessions, coalesce(sum(bounces), 0) as bounces, coalesce(sum(duration_sum), 0) as duration_sum')
            ->first();
        $dimension = $request->string('dimension')->toString();
        $definition = self::DIMENSIONS[$dimension] ?? null;
        $breakdown = [];
        if ($dimension === 'goals') {
            $goalTable = $daily === null ? 'stats_hourly_goals' : 'stats_daily_goals';
            $goalBucket = $daily === null ? 'hour' : 'day';
            $goalBounds = $daily === null ? [$hourFrom, $hourTo] : [$range['from'], $range['to']];
            $breakdown = DB::table($goalTable)
                ->join('goals', 'goals.id', '=', $goalTable.'.goal_id')
                ->where('goals.site_id', $site->id)
                ->whereBetween($goalTable.'.'.$goalBucket, $goalBounds)
                ->selectRaw('goals.name as `key`, sum(conversions) as conversions, sum('.$goalTable.'.visitors) as visitors')
                ->groupBy('goals.name')
                ->orderByDesc('conversions')
                ->limit(50)
                ->get()
                ->map(fn (object $row): array => [
                    'key' => $row->key,
                    'conversions' => (int) $row->conversions,
                    'visitors' => (int) $row->visitors,
                    'conversion_rate' => (int) $metrics->sessions === 0 ? 0 : round((int) $row->conversions * 100 / (int) $metrics->sessions, 2),
                ])
                ->all();
        } elseif ($definition !== null) {
            $breakdown = DB::table($definition['table'])
                ->where('site_id', $site->id)
                ->whereBetween('hour', [$hourFrom, $hourTo])
                ->selectRaw($definition['key'].' as `key`, sum('.$definition['metric'].') as pageviews, sum(visitors) as visitors')
                ->groupByRaw($definition['key'])
                ->orderByDesc('pageviews')
                ->limit(50)
                ->get()
                ->map(static fn (object $row): array => ['key' => $row->key, 'pageviews' => (int) $row->pageviews, 'visitors' => (int) $row->visitors])
                ->all();
        }

        return response()->json([
            'data' => [
                'pageviews' => (int) $metrics->pageviews,
                'visitors' => (int) $metrics->visitors,
                'sessions' => (int) $metrics->sessions,
                'bounces' => (int) $metrics->bounces,
                'duration_sum' => (int) $metrics->duration_sum,
                'views_per_session' => (int) $metrics->sessions === 0 ? 0 : round((int) $metrics->pageviews / (int) $metrics->sessions, 2),
                'bounce_rate' => (int) $metrics->sessions === 0 ? 0 : round((int) $metrics->bounces * 100 / (int) $metrics->sessions, 2),
                'average_session_duration' => (int) $metrics->sessions === 0 ? 0 : round((int) $metrics->duration_sum / (int) $metrics->sessions, 2),
                'breakdown_metric' => $dimension === 'goals' ? 'conversions' : 'pageviews',
                'breakdown' => $breakdown,
            ],
            'comparison' => [
                'pageviews' => (int) $comparison->pageviews,
                'visitors' => (int) $comparison->visitors,
                'sessions' => (int) $comparison->sessions,
                'bounces' => (int) $comparison->bounces,
                'duration_sum' => (int) $comparison->duration_sum,
            ],
            'meta' => [
                'visitor_label' => $daily === null ? 'visits (approximate)' : 'visitors',
                'comparison_visitor_label' => $comparisonDaily === null ? 'visits (approximate)' : 'visitors',
                'timezone' => $site->timezone,
                'operational' => $this->operationalState($site->id),
            ],
        ]);
    }

    public function realtime(Request $request, Tenant $tenant, string $siteId): JsonResponse
    {
        $until = (new \DateTimeImmutable($request->validate(['until' => ['required', 'date']])['until']))->setTimezone(new \DateTimeZone('UTC'));
        $site = Site::withoutGlobalScopes()->where(['tenant_id' => $tenant->id, 'public_id' => $siteId])->firstOrFail();
        $untilBucket = $until->setTime((int) $until->format('H'), intdiv((int) $until->format('i'), 5) * 5);
        $from = $untilBucket->sub(new \DateInterval('PT30M'))->format('Y-m-d H:i:00');

        $rows = DB::table('stats_realtime_five_minutes')
            ->where('site_id', $site->id)
            ->whereBetween('bucket', [$from, $untilBucket->format('Y-m-d H:i:00')])
            ->orderBy('bucket')
            ->get()
            ->map(static fn (object $row): array => ['bucket' => (new \DateTimeImmutable((string) $row->bucket.' UTC'))->format(DATE_ATOM), 'pageviews' => (int) $row->pageviews, 'events' => (int) $row->events, 'visitors' => (int) $row->visitors])
            ->all();

        return response()->json(['data' => $rows, 'meta' => ['window_minutes' => 30, 'bucket_window_minutes' => 35, 'timezone' => $site->timezone]]);
    }

    /** @return array{ingest:array{fresh:bool,last_seen_at:?string},cardinality_warning:bool,shed_events:int} */
    private function operationalState(int $siteId): array
    {
        $heartbeats = DB::table('system_heartbeats')
            ->whereIn('name', ['analytics:ingest', 'cardinality-cap:'.$siteId])
            ->get()
            ->keyBy('name');
        $ingest = $heartbeats->get('analytics:ingest');
        $lastSeen = $ingest?->last_seen_at === null ? null : new \DateTimeImmutable((string) $ingest->last_seen_at);
        $freshAfter = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->sub(new \DateInterval('PT3M'));
        $shedFile = storage_path('tm-buffer'.DIRECTORY_SEPARATOR.'tm-shed.count');
        $shedBytes = is_file($shedFile) ? (int) (@filesize($shedFile) ?: 0) : 0;

        return [
            'ingest' => ['fresh' => $ingest?->status === 'healthy' && $lastSeen !== null && $lastSeen->getTimestamp() >= $freshAfter->getTimestamp(), 'last_seen_at' => $lastSeen?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)],
            'cardinality_warning' => $heartbeats->has('cardinality-cap:'.$siteId),
            'shed_events' => intdiv($shedBytes, 2),
        ];
    }
}
