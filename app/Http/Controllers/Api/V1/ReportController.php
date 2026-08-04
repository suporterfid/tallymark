<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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

    public function show(Request $request, Tenant $tenant, string $siteId): JsonResponse
    {
        $range = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $site = Site::withoutGlobalScopes()->where(['tenant_id' => $tenant->id, 'public_id' => $siteId])->firstOrFail();
        $daily = $range['from'] === $range['to']
            ? DB::table('stats_daily_totals')->where(['site_id' => $site->id, 'day' => $range['from']])->first()
            : null;
        $metrics = $daily ?? DB::table('stats_hourly_totals')
            ->where('site_id', $site->id)
            ->whereBetween('hour', [$range['from'].' 00:00:00', $range['to'].' 23:59:59'])
            ->selectRaw('coalesce(sum(pageviews), 0) as pageviews, coalesce(sum(visitors), 0) as visitors, coalesce(sum(sessions), 0) as sessions, coalesce(sum(bounces), 0) as bounces, coalesce(sum(duration_sum), 0) as duration_sum')
            ->first();
        $from = new \DateTimeImmutable($range['from'].' UTC');
        $to = new \DateTimeImmutable($range['to'].' UTC');
        $days = $from->diff($to)->days + 1;
        $comparisonFrom = $from->sub(new \DateInterval('P'.$days.'D'))->format('Y-m-d');
        $comparisonTo = $from->sub(new \DateInterval('P1D'))->format('Y-m-d');
        $comparison = DB::table('stats_hourly_totals')
            ->where('site_id', $site->id)
            ->whereBetween('hour', [$comparisonFrom.' 00:00:00', $comparisonTo.' 23:59:59'])
            ->selectRaw('coalesce(sum(pageviews), 0) as pageviews, coalesce(sum(visitors), 0) as visitors, coalesce(sum(sessions), 0) as sessions, coalesce(sum(bounces), 0) as bounces, coalesce(sum(duration_sum), 0) as duration_sum')
            ->first();
        $dimension = $request->string('dimension')->toString();
        $definition = self::DIMENSIONS[$dimension] ?? null;
        $breakdown = [];
        if ($definition !== null) {
            $breakdown = DB::table($definition['table'])
                ->where('site_id', $site->id)
                ->whereBetween('hour', [$range['from'].' 00:00:00', $range['to'].' 23:59:59'])
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
                'breakdown' => $breakdown,
            ],
            'comparison' => [
                'pageviews' => (int) $comparison->pageviews,
                'visitors' => (int) $comparison->visitors,
                'sessions' => (int) $comparison->sessions,
                'bounces' => (int) $comparison->bounces,
                'duration_sum' => (int) $comparison->duration_sum,
            ],
            'meta' => ['visitor_label' => $daily === null ? 'visits (approximate)' : 'visitors', 'timezone' => $site->timezone],
        ]);
    }
}
