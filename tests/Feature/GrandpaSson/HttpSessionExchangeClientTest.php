<?php

namespace Tests\Feature\GrandpaSson;

use App\Application\GrandpaSson\HttpSessionExchangeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class HttpSessionExchangeClientTest extends TestCase
{
    public function test_it_redeems_the_browser_code_immediately_with_confidential_client_form_credentials(): void
    {
        config()->set('grandpasson.base_url', 'https://broker.example.test');
        config()->set('grandpasson.browser_client_id', 'tallymark-browser');
        config()->set('grandpasson.browser_client_secret', 'browser-secret');
        config()->set('grandpasson.redirect_uri', 'https://analytics.example.test/auth/grandpasson/callback');

        Http::fake([
            'https://broker.example.test/session/exchange' => Http::response([
                'subject' => ['id' => 'usr_broker', 'email' => 'member@example.test', 'name' => 'Broker Member', 'idp' => 'google'],
                'tenant' => ['id' => 'ten_analytics', 'slug' => 'analytics', 'role' => 'member'],
                'tenants' => [['id' => 'ten_analytics', 'slug' => 'analytics', 'role' => 'member']],
                'groups' => ['analytics-viewer'],
                'scopes' => ['openid', 'profile', 'email', 'tenant:read'],
            ]),
        ]);

        $session = app(HttpSessionExchangeClient::class)->exchange('broker-code', 'ten_analytics');

        self::assertSame('member@example.test', $session->email);
        self::assertSame('ten_analytics', $session->tenantId);
        self::assertSame(['analytics-viewer'], $session->groups);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://broker.example.test/session/exchange'
                && $request->data() === [
                    'code' => 'broker-code',
                    'client_id' => 'tallymark-browser',
                    'client_secret' => 'browser-secret',
                    'redirect_uri' => 'https://analytics.example.test/auth/grandpasson/callback',
                    'tenant' => 'ten_analytics',
                ];
        });
    }
}
