<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ingest\IngestRunner;
use Illuminate\Console\Command;

final class AnalyticsIngestCommand extends Command
{
    protected $signature = 'analytics:ingest';

    protected $description = 'Stage closed collector buffers for classification and aggregation.';

    public function handle(IngestRunner $ingestRunner): int
    {
        $ingestRunner->run();

        return self::SUCCESS;
    }
}
