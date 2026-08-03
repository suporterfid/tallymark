<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Shared\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $currentTime) {}

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function set(DateTimeImmutable $currentTime): void
    {
        $this->currentTime = $currentTime;
    }
}
