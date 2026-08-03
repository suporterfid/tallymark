<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Identity\SaltRotationService;
use Illuminate\Console\Command;

final class AnalyticsMaintenanceCommand extends Command
{
    protected $signature = 'analytics:maintenance';

    protected $description = 'Rotate visitor salts, regenerate the collector map, and record maintenance health.';

    public function handle(SaltRotationService $saltRotationService): int
    {
        $saltRotationService->maintain();

        return self::SUCCESS;
    }
}
