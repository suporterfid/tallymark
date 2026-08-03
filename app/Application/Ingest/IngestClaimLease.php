<?php

declare(strict_types=1);

namespace App\Application\Ingest;

use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use DateTimeImmutable;

final class IngestClaimLease
{
    public function release(int $batchId, string $claimToken): bool
    {
        return IngestBatch::query()
            ->whereKey($batchId)
            ->where('claim_token', $claimToken)
            ->update([
                'claim_token' => null,
                'claim_expires_at' => null,
            ]) === 1;
    }

    public function complete(int $batchId, string $claimToken, DateTimeImmutable $stagedAt): bool
    {
        return IngestBatch::query()
            ->whereKey($batchId)
            ->where('claim_token', $claimToken)
            ->update([
                'status' => 'staged',
                'claim_token' => null,
                'claim_expires_at' => null,
                'staged_at' => $stagedAt,
            ]) === 1;
    }
}
