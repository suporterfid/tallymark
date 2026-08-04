<?php

namespace Tests\Feature\GrandpaSson;

use Tests\TestCase;

final class GrandpaSsonConfigurationTest extends TestCase
{
    public function test_delegated_authentication_and_machine_introspection_are_disabled_by_default(): void
    {
        self::assertFalse(config('grandpasson.outbound_enabled'));
        self::assertFalse(config('grandpasson.inbound_enabled'));
        self::assertSame('analytics:read', config('grandpasson.read_scope'));
        self::assertSame('analytics:write', config('grandpasson.write_scope'));
        self::assertSame('analytics:callback', config('grandpasson.callback_scope'));
        self::assertSame(30, config('grandpasson.introspection_cache_seconds'));
    }
}
