<?php

namespace App\Providers;

use App\Application\Auth\GrandpaSsonIdentityProvider;
use App\Application\Auth\IdentityProvider;
use App\Application\Auth\LocalIdentityProvider;
use App\Application\GrandpaSson\CachedIntrospectionClient;
use App\Application\GrandpaSson\HttpIntrospectionClient;
use App\Application\GrandpaSson\HttpSessionExchangeClient;
use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Application\GrandpaSson\SessionExchangeClientInterface;
use App\Application\Sites\SiteMapGenerator;
use App\Application\Sites\SiteMapWriter;
use App\Application\TaskConnect\HttpTaskConnectTaskClient;
use App\Application\TaskConnect\TaskConnectDigestDelegator;
use App\Application\TaskConnect\TaskConnectTaskClientInterface;
use App\Application\Tenancy\TenantContext;
use App\Domain\Analytics\RegistrableDomainResolver;
use App\Domain\Shared\Clock;
use App\Infrastructure\Classification\PdpRegistrableDomainResolver;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Time\SystemClock;
use App\Policies\TenantPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Pdp\Rules;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(IdentityProvider::class, static function ($app): IdentityProvider {
            $local = new LocalIdentityProvider;

            return (bool) config('grandpasson.inbound_enabled', false)
                ? new GrandpaSsonIdentityProvider($local)
                : $local;
        });
        $this->app->bind(SessionExchangeClientInterface::class, HttpSessionExchangeClient::class);
        $this->app->bind(IntrospectionClientInterface::class, fn ($app): CachedIntrospectionClient => new CachedIntrospectionClient(
            new HttpIntrospectionClient,
            $app->make(Clock::class),
        ));
        $this->app->singleton(RegistrableDomainResolver::class, static fn (): PdpRegistrableDomainResolver => new PdpRegistrableDomainResolver(
            Rules::fromPath(resource_path('data/public_suffix_list.dat')),
        ));
        $this->app->bind(SiteMapWriter::class, SiteMapGenerator::class);
        $this->app->bind(TaskConnectTaskClientInterface::class, HttpTaskConnectTaskClient::class);
        $this->app->bind(TaskConnectDigestDelegator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
    }
}
