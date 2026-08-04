<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Aggregation;

use App\Domain\Aggregation\CardinalityGuard;
use PHPUnit\Framework\TestCase;

final class CardinalityGuardTest extends TestCase
{
    public function test_it_folds_a_new_value_when_the_bucket_cap_is_reached(): void
    {
        self::assertTrue(class_exists(CardinalityGuard::class));

        $decision = (new CardinalityGuard(2))->guard('/about', ['/pricing', '/docs']);

        self::assertSame('(other)', $decision->value);
        self::assertTrue($decision->folded);
    }

    public function test_it_preserves_an_existing_value_after_the_cap_is_reached(): void
    {
        self::assertTrue(class_exists(CardinalityGuard::class));

        $decision = (new CardinalityGuard(2))->guard('/pricing', ['/pricing', '/docs']);

        self::assertSame('/pricing', $decision->value);
        self::assertFalse($decision->folded);
    }
}
