<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

final readonly class UserAgentClassification
{
    public function __construct(
        public bool $isBot,
        public string $device,
        public string $browser,
        public string $os,
    ) {}
}
