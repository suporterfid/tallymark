<?php

namespace App\Application\GrandpaSson;

use App\Domain\Shared\Clock;
use Illuminate\Support\Facades\Cache;

final class CachedIntrospectionClient implements IntrospectionClientInterface
{
    public function __construct(
        private readonly IntrospectionClientInterface $inner,
        private readonly Clock $clock,
    ) {}

    public function introspect(string $token): IntrospectionResult
    {
        $key = 'grandpasson:introspection:'.hash('sha256', $token);
        $now = $this->clock->now()->getTimestamp();
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['expires_at'] ?? 0) > $now) {
            return new IntrospectionResult(
                active: (bool) ($cached['active'] ?? false),
                scopes: array_map('strval', (array) ($cached['scopes'] ?? [])),
                audiences: array_map('strval', (array) ($cached['audiences'] ?? [])),
                clientId: isset($cached['client_id']) ? (string) $cached['client_id'] : null,
                subject: isset($cached['subject']) ? (string) $cached['subject'] : null,
                expiresAtUnix: isset($cached['token_expires_at']) ? (int) $cached['token_expires_at'] : null,
            );
        }

        $fresh = $this->inner->introspect($token);
        $configuredTtl = max(0, (int) config('grandpasson.introspection_cache_seconds', 30));
        $expiresAt = $now + $configuredTtl;
        if ($fresh->expiresAtUnix !== null) {
            $expiresAt = min($expiresAt, $fresh->expiresAtUnix);
        }

        if ($expiresAt > $now) {
            Cache::put($key, [
                'active' => $fresh->active,
                'scopes' => $fresh->scopes,
                'audiences' => $fresh->audiences,
                'client_id' => $fresh->clientId,
                'subject' => $fresh->subject,
                'token_expires_at' => $fresh->expiresAtUnix,
                'expires_at' => $expiresAt,
            ], $expiresAt - $now);
        }

        return $fresh;
    }
}
