<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\UserAgentClassifier;
use PHPUnit\Framework\TestCase;

final class UserAgentClassifierTest extends TestCase
{
    public function test_it_derives_a_bot_without_retaining_the_user_agent(): void
    {
        self::assertTrue(class_exists(UserAgentClassifier::class));
        $classification = (new UserAgentClassifier())->classify('ExampleBot/1.0');

        self::assertTrue($classification->isBot);
        self::assertSame('bot', $classification->device);
        self::assertSame('bot', $classification->browser);
        self::assertSame('unknown', $classification->os);
    }

    public function test_it_derives_coarse_mobile_browser_and_os_families(): void
    {
        self::assertTrue(class_exists(UserAgentClassifier::class));
        $classification = (new UserAgentClassifier())->classify('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/124.0 Mobile Safari/537.36');

        self::assertFalse($classification->isBot);
        self::assertSame('mobile', $classification->device);
        self::assertSame('chrome', $classification->browser);
        self::assertSame('android', $classification->os);
    }
}
