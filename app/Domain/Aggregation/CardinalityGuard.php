<?php

declare(strict_types=1);

namespace App\Domain\Aggregation;

final class CardinalityGuard
{
    public function __construct(private readonly int $maximum) {}

    /** @param list<string> $existingValues */
    public function guard(string $value, array $existingValues): CardinalityDecision
    {
        if (in_array($value, $existingValues, true) || count($existingValues) < $this->maximum) {
            return new CardinalityDecision($value, false);
        }

        return new CardinalityDecision('(other)', true);
    }
}
