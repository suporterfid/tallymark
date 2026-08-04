<?php

declare(strict_types=1);

namespace App\Domain\Aggregation;

use DateTimeImmutable;

final readonly class SessionState
{
    public function __construct(
        public string $hour,
        public DateTimeImmutable $lastEventAt,
        public ?DateTimeImmutable $lastPageviewAt,
        public int $pageviews,
    ) {}
}
