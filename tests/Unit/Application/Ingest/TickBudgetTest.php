<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Ingest;

use App\Application\Ingest\TickBudget;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;

final class TickBudgetTest extends TestCase
{
    public function test_it_exits_cleanly_when_the_budget_is_exhausted(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-04 12:00:00 UTC'));
        $budget = new TickBudget($clock, 45);

        self::assertFalse($budget->exhausted());

        $clock->set(new DateTimeImmutable('2026-08-04 12:00:45 UTC'));

        self::assertTrue($budget->exhausted());
    }
}
