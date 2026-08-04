<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Goals\GoalManager;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Site;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

final class GoalController extends Controller
{
    public function index(Tenant $tenant, string $siteId): JsonResponse
    {
        $this->authorize('view', $tenant);

        return response()->json(['data' => $this->site($siteId)->goals()->orderBy('id')->get()]);
    }

    public function store(Request $request, Tenant $tenant, string $siteId, GoalManager $goalManager): JsonResponse
    {
        $this->authorize('update', $tenant);
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'event_name' => ['nullable', 'string', 'max:64'],
            'url_pattern' => ['nullable', 'string', 'max:512', 'regex:/^\\/[^?#]*$/'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            if ($request->filled('event_name') === $request->filled('url_pattern')) {
                $validator->errors()->add('matcher', __('goals.matcher'));
            }
        });
        $goal = $goalManager->create($this->site($siteId), $validator->validate());

        return response()->json(['data' => $goal], 201);
    }

    public function update(Request $request, Tenant $tenant, string $siteId, string $goalId, GoalManager $goalManager): JsonResponse
    {
        $this->authorize('update', $tenant);
        $goal = $this->goal($this->site($siteId), $goalId);

        return response()->json(['data' => $goalManager->update($goal, $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]))]);
    }

    public function destroy(Tenant $tenant, string $siteId, string $goalId, GoalManager $goalManager): Response
    {
        $this->authorize('update', $tenant);
        $goalManager->delete($this->goal($this->site($siteId), $goalId));

        return response()->noContent();
    }

    private function site(string $siteId): Site
    {
        return Site::query()->where('public_id', $siteId)->firstOrFail();
    }

    private function goal(Site $site, string $goalId): \App\Infrastructure\Persistence\Eloquent\Goal
    {
        return $site->goals()->where('public_id', $goalId)->firstOrFail();
    }
}
