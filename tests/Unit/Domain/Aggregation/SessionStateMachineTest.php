<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Aggregation;

use App\Domain\Aggregation\SessionState;
use App\Domain\Aggregation\SessionStateMachine;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionStateMachineTest extends TestCase
{
    public function test_it_starts_a_session_when_the_first_pageview_follows_a_custom_event(): void
    {
        $state = new SessionState(
            '2026-08-04 12:00:00',
            new DateTimeImmutable('2026-08-04 12:00:00 UTC'),
            null,
            0,
        );

        $transition = (new SessionStateMachine)->transition(
            $state,
            new DateTimeImmutable('2026-08-04 12:05:00 UTC'),
            'pageview',
        );

        self::assertSame(1, $transition->sessions);
        self::assertSame(1, $transition->bounces);
        self::assertSame(0, $transition->durationSum);
        self::assertSame(1, $transition->state->pageviews);
    }

    public function test_it_removes_the_bounce_and_accumulates_duration_on_a_second_pageview(): void
    {
        $state = new SessionState(
            '2026-08-04 12:00:00',
            new DateTimeImmutable('2026-08-04 12:00:00 UTC'),
            new DateTimeImmutable('2026-08-04 12:00:00 UTC'),
            1,
        );

        $transition = (new SessionStateMachine)->transition(
            $state,
            new DateTimeImmutable('2026-08-04 12:02:00 UTC'),
            'pageview',
        );

        self::assertSame(0, $transition->sessions);
        self::assertSame(-1, $transition->bounces);
        self::assertSame(120, $transition->durationSum);
        self::assertSame(2, $transition->state->pageviews);
    }
}
