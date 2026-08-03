<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Collection;

use App\Domain\Collection\EventLine;
use PHPUnit\Framework\TestCase;

final class EventLineTest extends TestCase
{
    public function test_it_rejects_ipv6_shaped_values_before_they_reach_staging(): void
    {
        $line = json_encode([
            'site_id' => 7,
            'visitor_id' => '0123456789abcdef',
            'timestamp' => '2026-08-04T12:00:00+00:00',
            'url' => 'https://example.test/2001:db8::1',
            'referrer' => null,
            'event' => 'pageview',
            'name' => '',
            'properties' => [],
        ], JSON_THROW_ON_ERROR);

        self::assertNull(EventLine::fromJson($line));
    }
}
