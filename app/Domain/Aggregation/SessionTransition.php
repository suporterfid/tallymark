<?php

declare(strict_types=1);

namespace App\Domain\Aggregation;

final readonly class SessionTransition
{
    public function __construct(
        public SessionState $state,
        public int $sessions,
        public int $bounces,
        public int $durationSum,
    ) {}
}
