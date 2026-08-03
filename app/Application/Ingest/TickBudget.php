<?php

declare(strict_types=1);

namespace App\Application\Ingest;

use App\Domain\Shared\Clock;
use DateTimeImmutable;

final class TickBudget
{
    private readonly DateTimeImmutable $startedAt;

    public function __construct(private readonly Clock $clock, private readonly int $seconds)
    {
        $this->startedAt = $clock->now();
    }

    public function exhausted(): bool
    {
        return $this->clock->now()->getTimestamp() >= $this->startedAt->getTimestamp() + $this->seconds;
    }
}
