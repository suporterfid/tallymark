<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;
use DateTimeZone;

final class Sessionizer
{
    public function __construct(private readonly int $gapMinutes = 30) {}

    /** @param list<SessionEvent> $events */
    public function sessionize(array $events): SessionizationResult
    {
        $bots = 0;
        $humanEvents = [];

        foreach ($events as $event) {
            if ($event->isBot) {
                $bots++;
                continue;
            }

            $humanEvents[] = $event;
        }

        usort($humanEvents, static fn (SessionEvent $left, SessionEvent $right): int => [
            $left->siteId,
            $left->visitorId,
            $left->occurredAt->getTimestamp(),
        ] <=> [
            $right->siteId,
            $right->visitorId,
            $right->occurredAt->getTimestamp(),
        ]);

        $sessions = [];
        $active = null;

        foreach ($humanEvents as $event) {
            if ($active === null || $this->startsNewSession($active, $event)) {
                if ($active !== null) {
                    $sessions[] = $this->summary($active);
                }

                $active = [
                    'event' => $event,
                    'startedAt' => $event->occurredAt,
                    'lastEventAt' => $event->occurredAt,
                    'firstPageviewAt' => $event->event === 'pageview' ? $event->occurredAt : null,
                    'endedAt' => $event->occurredAt,
                    'pageviews' => $event->event === 'pageview' ? 1 : 0,
                ];
                continue;
            }

            $active['lastEventAt'] = $event->occurredAt;
            if ($event->event === 'pageview') {
                $active['pageviews']++;
                $active['firstPageviewAt'] ??= $event->occurredAt;
                $active['endedAt'] = $event->occurredAt;
            }
        }

        if ($active !== null) {
            $sessions[] = $this->summary($active);
        }

        return new SessionizationResult($sessions, $bots);
    }

    /** @param array{event: SessionEvent, startedAt: DateTimeImmutable, lastEventAt: DateTimeImmutable, firstPageviewAt: ?DateTimeImmutable, endedAt: DateTimeImmutable, pageviews: int} $active */
    private function startsNewSession(array $active, SessionEvent $event): bool
    {
        $previous = $active['event'];
        if ($previous->siteId !== $event->siteId || $previous->visitorId !== $event->visitorId) {
            return true;
        }

        $utc = new DateTimeZone('UTC');
        if ($active['lastEventAt']->setTimezone($utc)->format('Y-m-d') !== $event->occurredAt->setTimezone($utc)->format('Y-m-d')) {
            return true;
        }

        return $event->occurredAt->getTimestamp() - $active['lastEventAt']->getTimestamp() > $this->gapMinutes * 60;
    }

    /** @param array{event: SessionEvent, startedAt: DateTimeImmutable, lastEventAt: DateTimeImmutable, firstPageviewAt: ?DateTimeImmutable, endedAt: DateTimeImmutable, pageviews: int} $active */
    private function summary(array $active): SessionSummary
    {
        return new SessionSummary($active['event']->siteId, $active['event']->visitorId, $active['firstPageviewAt'] ?? $active['startedAt'], $active['endedAt'], $active['pageviews']);
    }
}
