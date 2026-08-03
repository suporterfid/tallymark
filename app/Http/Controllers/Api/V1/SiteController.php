<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Sites\SiteManager;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Site::query()->with('hosts')->orderBy('id')->get()]);
    }

    public function store(Request $request, Tenant $tenant, SiteManager $siteManager): JsonResponse
    {
        $this->authorize('update', $tenant);
        $site = $siteManager->create($tenant, $this->validatedSite($request, true));

        return response()->json(['data' => $site], 201);
    }

    public function update(Request $request, Tenant $tenant, string $siteId, SiteManager $siteManager): JsonResponse
    {
        $this->authorize('update', $tenant);
        $site = $siteManager->update($this->site($siteId), $this->validatedSite($request, false));

        return response()->json(['data' => $site]);
    }

    public function destroy(Tenant $tenant, string $siteId, SiteManager $siteManager): Response
    {
        $this->authorize('update', $tenant);
        $siteManager->delete($this->site($siteId));

        return response()->noContent();
    }

    public function addHost(Request $request, Tenant $tenant, string $siteId, SiteManager $siteManager): JsonResponse
    {
        $this->authorize('update', $tenant);
        $host = $siteManager->addHost($this->site($siteId), $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
        ])['hostname']);

        return response()->json(['data' => $host], 201);
    }

    public function removeHost(Tenant $tenant, string $siteId, string $hostId, SiteManager $siteManager): Response
    {
        $this->authorize('update', $tenant);
        $siteManager->removeHost($this->site($siteId), $hostId);

        return response()->noContent();
    }

    public function rotateKey(Tenant $tenant, string $siteId, SiteManager $siteManager): JsonResponse
    {
        $this->authorize('update', $tenant);
        $site = $siteManager->rotateKey($this->site($siteId));

        return response()->json(['data' => $site]);
    }

    private function site(string $siteId): Site
    {
        return Site::query()->where('public_id', $siteId)->firstOrFail();
    }

    /** @return array{name?: string, timezone?: string, hosts?: list<string>, is_public?: bool, exclude_rules?: array<mixed>|null, sample?: int, validate_host?: bool} */
    private function validatedSite(Request $request, bool $creating): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'timezone' => [$creating ? 'required' : 'sometimes', 'timezone'],
            'is_public' => ['sometimes', 'boolean'],
            'exclude_rules' => ['sometimes', 'nullable', 'array'],
            'sample' => ['sometimes', 'integer', 'between:1,100'],
            'validate_host' => ['sometimes', 'boolean'],
        ];

        if ($creating) {
            $rules['hosts'] = ['required', 'array', 'min:1'];
            $rules['hosts.*'] = ['string', 'max:253'];
        }

        return $request->validate($rules);
    }
}
