<?php

declare(strict_types=1);

namespace App\Domain\Aggregation;

final readonly class CardinalityDecision
{
    public function __construct(public string $value, public bool $folded) {}
}
