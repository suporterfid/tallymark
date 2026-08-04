<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Rollups\RollupService;
use Illuminate\Console\Command;

final class AnalyticsRollupCommand extends Command
{
    protected $signature = 'analytics:rollup {--day=}';

    protected $description = 'Roll closed hourly analytics counters into daily summaries.';

    public function handle(RollupService $rollupService): int
    {
        $rollupService->rollup($this->option('day'));

        return self::SUCCESS;
    }
}
