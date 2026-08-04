<?php

namespace App\Application\GrandpaSson;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpSessionExchangeClient implements SessionExchangeClientInterface
{
    public function exchange(string $code, ?string $tenant = null): GrandpaSsonSession
    {
        $baseUrl = rtrim((string) config('grandpasson.base_url'), '/');
        $clientId = (string) config('grandpasson.browser_client_id');
        $clientSecret = (string) config('grandpasson.browser_client_secret');
        $redirectUri = (string) config('grandpasson.redirect_uri');

        if ($baseUrl === '' || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('GrandpaSSOn browser exchange is not configured.');
        }

        $body = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ];
        if ($tenant !== null && $tenant !== '') {
            $body['tenant'] = $tenant;
        }

        $response = Http::asForm()->timeout(10)->post($baseUrl.'/session/exchange', $body);
        if (! $response->successful()) {
            throw new RuntimeException('GrandpaSSOn session exchange was rejected.');
        }

        $subject = $response->json('subject');
        if (! is_array($subject)) {
            $subject = [
                'id' => $response->json('id'),
                'email' => $response->json('email'),
                'name' => $response->json('display_name'),
            ];
        }

        $subjectId = is_string($subject['id'] ?? null) ? $subject['id'] : '';
        $email = is_string($subject['email'] ?? null) ? $subject['email'] : '';
        if ($subjectId === '' || $email === '') {
            throw new RuntimeException('GrandpaSSOn session exchange response is malformed.');
        }

        $brokerTenant = $response->json('tenant');
        $tenants = $response->json('tenants', []);

        return new GrandpaSsonSession(
            subjectId: $subjectId,
            email: $email,
            name: is_string($subject['name'] ?? null) ? $subject['name'] : '',
            identityProvider: is_string($subject['idp'] ?? null) ? $subject['idp'] : null,
            tenantId: is_array($brokerTenant) && is_string($brokerTenant['id'] ?? null) ? $brokerTenant['id'] : null,
            tenants: is_array($tenants) ? $tenants : [],
            groups: array_values(array_map('strval', (array) $response->json('groups', []))),
            scopes: array_values(array_map('strval', (array) $response->json('scopes', []))),
        );
    }
}
