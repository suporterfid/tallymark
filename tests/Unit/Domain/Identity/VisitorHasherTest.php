<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity;

use App\Domain\Identity\VisitorHasher;
use PHPUnit\Framework\TestCase;

final class VisitorHasherTest extends TestCase
{
    public function test_it_derives_a_stable_64_bit_visitor_id_from_the_daily_salt_and_request_identity(): void
    {
        self::assertTrue(class_exists(VisitorHasher::class));

        $hasher = new VisitorHasher();
        $salt = str_repeat('a', 64);

        $visitorId = $hasher->hash($salt, 7, '203.0.113.42', 'ExampleBrowser/1.0');

        self::assertSame($visitorId, $hasher->hash($salt, 7, '203.0.113.42', 'ExampleBrowser/1.0'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $visitorId);
        self::assertNotSame($visitorId, $hasher->hash($salt, 8, '203.0.113.42', 'ExampleBrowser/1.0'));
        self::assertNotSame($visitorId, $hasher->hash(str_repeat('b', 64), 7, '203.0.113.42', 'ExampleBrowser/1.0'));
    }
}
