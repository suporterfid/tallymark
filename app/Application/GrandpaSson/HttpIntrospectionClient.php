<?php

namespace App\Application\GrandpaSson;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpIntrospectionClient implements IntrospectionClientInterface
{
    public function introspect(string $token): IntrospectionResult
    {
        $url = (string) config('grandpasson.introspect_url');
        if ($url === '') {
            throw new RuntimeException('GrandpaSSOn introspection URL is not configured.');
        }

        $response = Http::asForm()->timeout(10)->post($url, [
            'client_id' => (string) config('grandpasson.machine_client_id'),
            'client_secret' => (string) config('grandpasson.machine_client_secret'),
            'token' => $token,
        ]);

        if (! $response->successful()) {
            return new IntrospectionResult(active: false);
        }

        $scope = $response->json('scope', '');
        $audience = $response->json('aud', []);
        $expiresAt = $response->json('exp');

        return new IntrospectionResult(
            active: (bool) $response->json('active', false),
            scopes: is_string($scope) ? (preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) ?: []) : array_map('strval', (array) $scope),
            audiences: is_string($audience) ? [$audience] : array_map('strval', (array) $audience),
            clientId: is_string($response->json('client_id')) ? $response->json('client_id') : null,
            subject: is_string($response->json('sub')) ? $response->json('sub') : null,
            expiresAtUnix: is_numeric($expiresAt) ? (int) $expiresAt : null,
        );
    }
}
