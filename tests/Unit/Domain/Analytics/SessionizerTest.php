<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\SessionEvent;
use App\Domain\Analytics\Sessionizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionizerTest extends TestCase
{
    public function test_it_splits_a_visitor_session_at_utc_midnight_even_when_the_gap_is_short(): void
    {
        self::assertTrue(class_exists(Sessionizer::class));
        $result = (new Sessionizer())->sessionize([
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 23:59:50 UTC'), 'pageview', false),
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-05 00:00:10 UTC'), 'pageview', false),
        ]);

        self::assertCount(2, $result->sessions());
        self::assertSame(1, $result->sessions()[0]->pageviews);
        self::assertTrue($result->sessions()[0]->bounce);
        self::assertSame(0, $result->sessions()[0]->durationSeconds);
        self::assertSame('2026-08-05T00:00:10+00:00', $result->sessions()[1]->startedAt->format(DATE_ATOM));
    }

    public function test_it_excludes_bot_events_and_counts_them_separately(): void
    {
        self::assertTrue(class_exists(Sessionizer::class));
        $result = (new Sessionizer())->sessionize([
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 12:00:00 UTC'), 'pageview', true),
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 12:01:00 UTC'), 'pageview', false),
        ]);

        self::assertSame(1, $result->botEvents());
        self::assertCount(1, $result->sessions());
        self::assertSame(1, $result->sessions()[0]->pageviews);
    }

    public function test_it_measures_duration_to_the_last_pageview_not_a_later_custom_event(): void
    {
        $result = (new Sessionizer())->sessionize([
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 12:00:00 UTC'), 'pageview', false),
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 12:10:00 UTC'), 'signup', false),
        ]);

        self::assertSame(0, $result->sessions()[0]->durationSeconds);
    }

    public function test_it_measures_duration_from_the_first_pageview_not_an_earlier_custom_event(): void
    {
        $result = (new Sessionizer())->sessionize([
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 12:00:00 UTC'), 'signup', false),
            new SessionEvent(7, '0123456789abcdef', new DateTimeImmutable('2026-08-04 12:05:00 UTC'), 'pageview', false),
        ]);

        self::assertSame(0, $result->sessions()[0]->durationSeconds);
    }
}
