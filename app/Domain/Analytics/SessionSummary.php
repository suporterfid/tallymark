<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;

final readonly class SessionSummary
{
    public function __construct(
        public int $siteId,
        public string $visitorId,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $endedAt,
        public int $pageviews,
    ) {}

    public function __get(string $name): mixed
    {
        return match ($name) {
            'bounce' => $this->pageviews === 1,
            'durationSeconds' => max(0, $this->endedAt->getTimestamp() - $this->startedAt->getTimestamp()),
            default => throw new \LogicException('Undefined session property: '.$name),
        };
    }
}
