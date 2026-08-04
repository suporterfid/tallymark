<?php

namespace Tests\Feature\GrandpaSson;

use App\Application\GrandpaSson\HttpIntrospectionClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class HttpIntrospectionClientTest extends TestCase
{
    public function test_it_posts_broker_credentials_and_token_in_the_form_body(): void
    {
        config()->set('grandpasson.introspect_url', 'https://broker.example.test/oauth/introspect');
        config()->set('grandpasson.machine_client_id', 'tallymark-machine');
        config()->set('grandpasson.machine_client_secret', 'machine-secret');

        Http::fake([
            'https://broker.example.test/oauth/introspect' => Http::response([
                'active' => true,
                'scope' => 'analytics:read analytics:write',
                'aud' => 'workspace/ten_analytics',
                'client_id' => 'tallymark-machine',
                'sub' => 'usr_broker_subject',
                'exp' => 1_800_000_000,
            ]),
        ]);

        $result = app(HttpIntrospectionClient::class)->introspect('gpat_live_example');

        self::assertTrue($result->active);
        self::assertTrue($result->hasScope('analytics:write'));
        self::assertTrue($result->audienceIncludes('ten_analytics'));
        self::assertSame(1_800_000_000, $result->expiresAtUnix);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://broker.example.test/oauth/introspect'
                && $request->data() === [
                    'client_id' => 'tallymark-machine',
                    'client_secret' => 'machine-secret',
                    'token' => 'gpat_live_example',
                ]
                && ! $request->hasHeader('Authorization');
        });
    }
}
