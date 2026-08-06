<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\SharedDashboards\SharedDashboardManager;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\SharedDashboard;
use Illuminate\Http\Response;

final class SharedDashboardController extends Controller
{
    public function show(string $dashboard, SharedDashboardManager $sharedDashboards): Response
    {
        $sharedDashboard = SharedDashboard::query()->where(['public_id' => $dashboard, 'is_enabled' => true])->firstOrFail();

        return response($sharedDashboards->render($sharedDashboard));
    }
}
