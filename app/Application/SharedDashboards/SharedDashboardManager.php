<?php

declare(strict_types=1);

namespace App\Application\SharedDashboards;

use App\Domain\Shared\PublicId;
use App\Infrastructure\Persistence\Eloquent\SharedDashboard;
use App\Infrastructure\Persistence\Eloquent\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SharedDashboardManager
{
    public function enable(Site $site): SharedDashboard
    {
        $dashboard = $site->sharedDashboard()->firstOrCreate([], ['is_enabled' => true]);
        if (! $dashboard->is_enabled) {
            $dashboard->update(['is_enabled' => true]);
        }
        Cache::forget($this->cacheKey($dashboard));

        return $dashboard->fresh();
    }

    public function render(SharedDashboard $dashboard): string
    {
        return Cache::remember($this->cacheKey($dashboard), 300, function () use ($dashboard): string {
            $site = Site::withoutGlobalScopes()->findOrFail($dashboard->site_id, ['id', 'name']);
            $totals = DB::table('stats_daily_totals')
                ->where('site_id', $site->id)
                ->selectRaw('coalesce(sum(pageviews), 0) as pageviews, coalesce(sum(sessions), 0) as sessions')
                ->first();
            $conversions = DB::table('stats_daily_goals')
                ->where('site_id', $site->id)
                ->sum('conversions');

            return view('shared.dashboard', [
                'siteName' => $site->name,
                'pageviews' => (int) $totals->pageviews,
                'sessions' => (int) $totals->sessions,
                'conversions' => (int) $conversions,
            ])->render();
        });
    }

    public function setEnabled(Site $site, bool $isEnabled): SharedDashboard
    {
        $dashboard = $site->sharedDashboard()->firstOrFail();
        $dashboard->update(['is_enabled' => $isEnabled]);
        Cache::forget($this->cacheKey($dashboard));

        return $dashboard->fresh();
    }

    public function rotate(Site $site): SharedDashboard
    {
        $dashboard = $site->sharedDashboard()->firstOrFail();
        Cache::forget($this->cacheKey($dashboard));
        $dashboard->public_id = PublicId::generate('dash');
        $dashboard->is_enabled = true;
        $dashboard->save();

        return $dashboard->fresh();
    }

    private function cacheKey(SharedDashboard $dashboard): string
    {
        return 'shared-dashboard:'.$dashboard->public_id;
    }
}
