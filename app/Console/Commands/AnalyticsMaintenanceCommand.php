<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Identity\SaltRotationService;
use App\Application\Rollups\RetentionService;
use Illuminate\Console\Command;

final class AnalyticsMaintenanceCommand extends Command
{
    protected $signature = 'analytics:maintenance';

    protected $description = 'Rotate visitor salts, regenerate the collector map, record maintenance health, and prune rolled-up data.';

    public function handle(SaltRotationService $saltRotationService, RetentionService $retentionService): int
    {
        $saltRotationService->maintain();
        $retentionService->prune();

        return self::SUCCESS;
    }
}
