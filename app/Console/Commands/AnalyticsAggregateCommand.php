<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Aggregation\AggregationRunner;
use Illuminate\Console\Command;

final class AnalyticsAggregateCommand extends Command
{
    protected $signature = 'analytics:aggregate';
    protected $description = 'Aggregate staged analytics events into hourly counters';

    public function handle(AggregationRunner $runner): int
    {
        $runner->run();

        return self::SUCCESS;
    }
}
