<?php

namespace App\Providers;

use App\Application\Tenancy\TenantContext;
use App\Application\Sites\SiteMapGenerator;
use App\Application\Sites\SiteMapWriter;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Time\SystemClock;
use App\Policies\TenantPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(SiteMapWriter::class, SiteMapGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
    }
}
