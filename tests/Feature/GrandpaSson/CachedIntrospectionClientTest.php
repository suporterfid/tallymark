<?php

namespace Tests\Feature\GrandpaSson;

use App\Application\GrandpaSson\CachedIntrospectionClient;
use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Application\GrandpaSson\IntrospectionResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class CachedIntrospectionClientTest extends TestCase
{
    public function test_it_caches_by_token_fingerprint_until_the_earlier_of_configured_ttl_and_expiry(): void
    {
        config()->set('cache.default', 'array');
        config()->set('grandpasson.introspection_cache_seconds', 30);
        Cache::flush();

        $clock = new FixedClock(new DateTimeImmutable('@1800000000'));
        $inner = new class implements IntrospectionClientInterface
        {
            public int $calls = 0;

            public function introspect(string $token): IntrospectionResult
            {
                $this->calls++;

                return new IntrospectionResult(
                    active: true,
                    scopes: ['analytics:read'],
                    audiences: ['workspace/ten_analytics'],
                    expiresAtUnix: 1800000020,
                );
            }
        };
        $client = new CachedIntrospectionClient($inner, $clock);

        self::assertTrue($client->introspect('gpat_live_example')->active);
        self::assertTrue($client->introspect('gpat_live_example')->active);
        self::assertSame(1, $inner->calls);
        self::assertNotNull(Cache::get('grandpasson:introspection:'.hash('sha256', 'gpat_live_example')));
        self::assertNull(Cache::get('grandpasson:introspection:gpat_live_example'));

        $clock->set(new DateTimeImmutable('@1800000021'));

        self::assertTrue($client->introspect('gpat_live_example')->active);
        self::assertSame(2, $inner->calls);
    }
}
