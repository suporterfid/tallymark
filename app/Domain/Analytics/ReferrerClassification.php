<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

final readonly class ReferrerClassification
{
    public function __construct(public string $source, public bool $spam) {}
}
