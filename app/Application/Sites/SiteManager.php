<?php

namespace App\Application\Sites;

use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\SiteHost;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Support\Facades\DB;

final class SiteManager
{
    public function __construct(
        private readonly SiteKeyGenerator $siteKeyGenerator,
        private readonly SiteMapWriter $siteMapGenerator,
    ) {}

    /** @param array{name: string, timezone: string, hosts: list<string>, is_public?: bool, exclude_rules?: array<mixed>, sample?: int, validate_host?: bool} $attributes */
    public function create(Tenant $tenant, array $attributes): Site
    {
        $siteId = DB::transaction(function () use ($tenant, $attributes): int {
            $site = Site::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $attributes['name'],
                'timezone' => $attributes['timezone'],
                'site_key' => $this->siteKeyGenerator->generate(),
                'is_public' => $attributes['is_public'] ?? false,
                'exclude_rules' => $attributes['exclude_rules'] ?? null,
                'sample' => $attributes['sample'] ?? 100,
                'validate_host' => $attributes['validate_host'] ?? true,
            ]);

            foreach ($attributes['hosts'] as $hostname) {
                $this->addHostRecord($site, $hostname);
            }

            return $site->id;
        });

        $this->siteMapGenerator->regenerate();

        return $this->freshSite($siteId);
    }

    /** @param array{name?: string, timezone?: string, is_public?: bool, exclude_rules?: array<mixed>|null, sample?: int, validate_host?: bool} $attributes */
    public function update(Site $site, array $attributes): Site
    {
        $siteId = DB::transaction(function () use ($site, $attributes): int {
            $site->fill($attributes);
            $site->save();

            return $site->id;
        });

        $this->siteMapGenerator->regenerate();

        return $this->freshSite($siteId);
    }

    public function addHost(Site $site, string $hostname): SiteHost
    {
        $hostId = DB::transaction(function () use ($site, $hostname): int {
            $host = $this->addHostRecord($site, $hostname);

            return $host->id;
        });

        $this->siteMapGenerator->regenerate();

        return SiteHost::withoutGlobalScopes()->findOrFail($hostId);
    }

    public function removeHost(Site $site, string $hostPublicId): void
    {
        DB::transaction(function () use ($site, $hostPublicId): void {
            $site->hosts()->where('public_id', $hostPublicId)->firstOrFail()->delete();
        });

        $this->siteMapGenerator->regenerate();
    }

    public function rotateKey(Site $site): Site
    {
        $siteId = DB::transaction(function () use ($site): int {
            $site->site_key = $this->siteKeyGenerator->generate();
            $site->save();

            return $site->id;
        });

        $this->siteMapGenerator->regenerate();

        return $this->freshSite($siteId);
    }

    public function delete(Site $site): void
    {
        DB::transaction(function () use ($site): void {
            $site->delete();
        });

        $this->siteMapGenerator->regenerate();
    }

    private function addHostRecord(Site $site, string $hostname): SiteHost
    {
        return SiteHost::query()->create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'hostname' => strtolower(rtrim($hostname, '.')),
        ]);
    }

    private function freshSite(int $siteId): Site
    {
        return Site::withoutGlobalScopes()
            ->with(['hosts' => fn ($query) => $query->withoutGlobalScopes()->orderBy('hostname')])
            ->findOrFail($siteId);
    }
}
