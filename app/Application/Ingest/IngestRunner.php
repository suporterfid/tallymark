<?php

declare(strict_types=1);

namespace App\Application\Ingest;

use App\Domain\Collection\EventLine;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use App\Infrastructure\Persistence\Eloquent\IngestEvent;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IngestRunner
{
    public function __construct(
        private readonly Clock $clock,
        private readonly BufferReader $bufferReader,
        private readonly IngestClaimLease $claimLease,
    ) {}

    public function run(): void
    {
        $budget = new TickBudget($this->clock, $this->budgetSeconds());

        foreach ($this->bufferReader->closedFiles($this->clock->now()) as $path) {
            if ($budget->exhausted()) {
                return;
            }

            $this->stage($path, $budget);
        }
    }

    private function stage(string $path, TickBudget $budget): void
    {
        $filename = basename($path);
        $now = $this->clock->now();
        $batch = $this->claim($filename, $now);

        if ($batch === null) {
            return;
        }

        $claimToken = (string) $batch->claim_token;

        if ($claimToken === '') {
            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->claimLease->release($batch->id, $claimToken);

            return;
        }

        try {
            $lineNumber = 0;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($budget->exhausted()) {
                    $this->claimLease->release($batch->id, $claimToken);

                    return;
                }

                if ($lineNumber <= $batch->next_line) {
                    continue;
                }

                $batch = $this->stageLine($batch, $claimToken, $lineNumber, rtrim($line, "\r\n"));

                if ($batch === null) {
                    return;
                }
            }

            if (! $this->claimLease->complete($batch->id, $claimToken, $now)) {
                return;
            }
        } finally {
            fclose($handle);
        }

        @unlink($path);
    }

    private function stageLine(IngestBatch $batch, string $claimToken, int $lineNumber, string $line): ?IngestBatch
    {
        return DB::transaction(function () use ($batch, $claimToken, $lineNumber, $line): ?IngestBatch {
            $current = IngestBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($current->claim_token !== $claimToken) {
                return null;
            }

            if ($current->next_line >= $lineNumber) {
                return $current;
            }

            $event = EventLine::fromJson($line);
            $updates = ['next_line' => $lineNumber];

            if ($event === null) {
                $updates['malformed_lines'] = $current->malformed_lines + 1;
            } else {
                IngestEvent::query()->firstOrCreate([
                    'ingest_batch_id' => $current->id,
                    'line_number' => $lineNumber,
                ], ['payload' => $event->toArray()]);
                $updates['accepted_lines'] = $current->accepted_lines + 1;
            }

            $current->update($updates);

            return $current;
        });
    }

    private function claim(string $filename, DateTimeImmutable $now): ?IngestBatch
    {
        try {
            return DB::transaction(function () use ($filename, $now): ?IngestBatch {
                $batch = IngestBatch::query()->where('filename', $filename)->lockForUpdate()->first();

                if ($batch !== null) {
                    if ($batch->status === 'staged') {
                        return null;
                    }

                    if (
                        $batch->status === 'processing'
                        && $batch->claim_expires_at !== null
                        && $batch->claim_expires_at->getTimestamp() > $now->getTimestamp()
                    ) {
                        return null;
                    }

                    $batch->update([
                        'status' => 'processing',
                        'claim_token' => (string) Str::uuid(),
                        'claim_expires_at' => $now->modify('+10 minutes'),
                    ]);

                    return $batch;
                }

                return IngestBatch::query()->create([
                    'filename' => $filename,
                    'status' => 'processing',
                    'claim_token' => (string) Str::uuid(),
                    'claim_expires_at' => $now->modify('+10 minutes'),
                ]);
            });
        } catch (QueryException) {
            return null;
        }
    }

    private function budgetSeconds(): int
    {
        $configured = (int) (getenv('TM_INGEST_TARGET_SECONDS') ?: 45);
        $maximum = (int) ini_get('max_execution_time');

        if ($maximum > 0) {
            return max(1, min($configured, max(1, $maximum - 5)));
        }

        return max(1, $configured);
    }
}
