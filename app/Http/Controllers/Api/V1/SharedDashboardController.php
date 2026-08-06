<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\SharedDashboards\SharedDashboardManager;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SharedDashboardController extends Controller
{
    public function store(Tenant $tenant, string $siteId, SharedDashboardManager $sharedDashboards): JsonResponse
    {
        $this->authorize('update', $tenant);

        return response()->json(['data' => $sharedDashboards->enable($this->site($siteId))], 201);
    }

    public function update(Request $request, Tenant $tenant, string $siteId, SharedDashboardManager $sharedDashboards): JsonResponse
    {
        $this->authorize('update', $tenant);
        $isEnabled = $request->validate(['is_enabled' => ['required', 'boolean']])['is_enabled'];

        return response()->json(['data' => $sharedDashboards->setEnabled($this->site($siteId), $isEnabled)]);
    }

    public function rotate(Tenant $tenant, string $siteId, SharedDashboardManager $sharedDashboards): JsonResponse
    {
        $this->authorize('update', $tenant);

        return response()->json(['data' => $sharedDashboards->rotate($this->site($siteId))]);
    }

    private function site(string $siteId): Site
    {
        return Site::query()->where('public_id', $siteId)->firstOrFail();
    }
}
