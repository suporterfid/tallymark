<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

final readonly class SessionizationResult
{
    /** @param list<SessionSummary> $sessions */
    public function __construct(private array $sessions, private int $botEvents) {}

    /** @return list<SessionSummary> */
    public function sessions(): array
    {
        return $this->sessions;
    }

    public function botEvents(): int
    {
        return $this->botEvents;
    }
}
