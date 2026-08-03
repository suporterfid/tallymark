<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;

final readonly class SessionEvent
{
    public function __construct(
        public int $siteId,
        public string $visitorId,
        public DateTimeImmutable $occurredAt,
        public string $event,
        public bool $isBot,
    ) {}
}
