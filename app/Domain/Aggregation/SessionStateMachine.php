<?php

declare(strict_types=1);

namespace App\Domain\Aggregation;

use DateTimeImmutable;
use DateTimeZone;

final class SessionStateMachine
{
    public function __construct(private readonly int $gapSeconds = 1800) {}

    public function transition(?SessionState $state, DateTimeImmutable $occurredAt, string $event): SessionTransition
    {
        $isPageview = $event === 'pageview';

        if ($state === null || $occurredAt->getTimestamp() - $state->lastEventAt->getTimestamp() > $this->gapSeconds) {
            return new SessionTransition(
                new SessionState(
                    $this->hour($occurredAt),
                    $occurredAt,
                    $isPageview ? $occurredAt : null,
                    $isPageview ? 1 : 0,
                ),
                $isPageview ? 1 : 0,
                $isPageview ? 1 : 0,
                0,
            );
        }

        if (! $isPageview) {
            return new SessionTransition(
                new SessionState($state->hour, $occurredAt, $state->lastPageviewAt, $state->pageviews),
                0,
                0,
                0,
            );
        }

        if ($state->pageviews === 0) {
            return new SessionTransition(
                new SessionState($state->hour, $occurredAt, $occurredAt, 1),
                1,
                1,
                0,
            );
        }

        return new SessionTransition(
            new SessionState($state->hour, $occurredAt, $occurredAt, $state->pageviews + 1),
            0,
            $state->pageviews === 1 ? -1 : 0,
            $occurredAt->getTimestamp() - $state->lastPageviewAt->getTimestamp(),
        );
    }

    private function hour(DateTimeImmutable $occurredAt): string
    {
        return $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:00:00');
    }
}
