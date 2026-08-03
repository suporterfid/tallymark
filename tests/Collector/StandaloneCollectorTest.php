<?php

declare(strict_types=1);

namespace Tests\Collector;

use App\Domain\Collection\EventLine;
use App\Domain\Analytics\UserAgentClassifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class StandaloneCollectorTest extends TestCase
{
    private string $root;
    private string $runtimeRoot;
    private string $storage;
    private string $bufferDirectory;
    private ?Process $server = null;
    private int $port;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->runtimeRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tallymark-collector-'.bin2hex(random_bytes(8));
        $this->storage = $this->runtimeRoot.DIRECTORY_SEPARATOR.'storage';
        $this->bufferDirectory = $this->storage.DIRECTORY_SEPARATOR.'tm-buffer';
        $this->port = $this->availablePort();

        mkdir($this->runtimeRoot.DIRECTORY_SEPARATOR.'public', 0775, true);
        mkdir($this->bufferDirectory, 0775, true);
        copy($this->root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'px.php', $this->runtimeRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'px.php');
        file_put_contents($this->storage.DIRECTORY_SEPARATOR.'tm-sites.php', "<?php\n\nreturn ".var_export([
            'salt' => str_repeat('a', 64),
            'sites' => [
                'known-site-key' => [
                    'id' => 7,
                    'hosts' => ['example.test', 'www.example.test'],
                    'sample' => 100,
                    'validate_host' => true,
                ],
            ],
        ], true).";\n");

        $this->server = new Process([
            PHP_BINARY,
            '-S',
            '127.0.0.1:'.$this->port,
            '-t',
            $this->runtimeRoot.DIRECTORY_SEPARATOR.'public',
        ], $this->runtimeRoot, [
            'TM_BUFFER_SHARDS' => '1',
            'TM_MAX_BODY_BYTES' => '2048',
            'TM_BUFFER_MAX_MB' => '64',
            'TM_MAX_LINES_PER_MINUTE' => '20000',
            'TM_RESPECT_DNT' => '1',
        ]);
        $this->server->start();
        $this->waitForServer();
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->removeCollectorRuntimeFiles();
        rmdir($this->runtimeRoot.DIRECTORY_SEPARATOR.'public');
        rmdir($this->storage);
        rmdir($this->runtimeRoot);
    }

    public function test_collector_is_standalone_sanitizes_events_and_stays_fast(): void
    {
        $collector = (string) file_get_contents($this->root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'px.php');

        self::assertStringNotContainsString('vendor/autoload.php', $collector);
        self::assertDoesNotMatchRegularExpression('/\b(?:pdo|mysqli)\b/i', $collector);

        $payload = [
            'k' => 'known-site-key',
            'u' => 'https://example.test/pricing?utm_source=search&email=private@example.test#fragment',
            'r' => 'https://referrer.test/path?utm_medium=partner&secret=discard',
            'e' => 'pageview',
            'n' => 'Signup',
            'p' => ['plan' => 'pro'],
        ];
        $response = $this->request($payload, [
            'Origin: https://example.test',
            'User-Agent: RawAgent/9.9',
            'X-Forwarded-For: 203.0.113.42',
        ]);

        self::assertSame(204, $response['status']);
        self::assertSame('', $response['body']);
        self::assertContains('Access-Control-Allow-Origin: *', $response['headers']);
        self::assertFalse((bool) preg_grep('/^Set-Cookie:/i', $response['headers']));

        $lines = $this->bufferLines();
        self::assertCount(1, $lines);
        self::assertStringNotContainsString('203.0.113.42', $lines[0]);
        self::assertStringNotContainsString('RawAgent/9.9', $lines[0]);
        $event = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(7, $event['site_id']);
        self::assertArrayHasKey('visitor_id', $event);
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $event['visitor_id']);
        $applicationLine = EventLine::fromJson($lines[0]);
        self::assertNotNull($applicationLine);
        self::assertSame($event, json_decode($applicationLine->toJson(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame('https://example.test/pricing?utm_source=search', $event['url']);
        self::assertSame('https://referrer.test/path?utm_medium=partner', $event['referrer']);
        self::assertFalse($event['is_bot']);
        self::assertSame('unknown', $event['device']);
        $applicationClassification = (new UserAgentClassifier())->classify('RawAgent/9.9');
        self::assertSame($applicationClassification->isBot, $event['is_bot']);
        self::assertSame($applicationClassification->device, $event['device']);
        self::assertSame($applicationClassification->browser, $event['browser']);
        self::assertSame($applicationClassification->os, $event['os']);

        $unknown = $this->request(['k' => 'unknown-key', 'u' => 'https://example.test/']);
        self::assertSame(204, $unknown['status']);
        self::assertCount(1, $this->bufferLines());

        $mismatchedHost = $this->request($payload, ['Origin: https://attacker.test']);
        self::assertSame(204, $mismatchedHost['status']);
        self::assertCount(1, $this->bufferLines());

        $bot = $this->request($payload, ['Origin: https://example.test', 'User-Agent: test-crawler']);
        self::assertSame(204, $bot['status']);
        self::assertCount(2, $this->bufferLines());
        $botEvent = json_decode($this->bufferLines()[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($botEvent['is_bot']);
        self::assertSame('bot', $botEvent['device']);

        $dnt = $this->request($payload, ['Origin: https://example.test', 'DNT: 1']);
        self::assertSame(204, $dnt['status']);
        self::assertCount(2, $this->bufferLines());

        $oversized = $this->requestRaw(str_repeat('x', 2049), ['Content-Type: text/plain', 'Origin: https://example.test']);
        self::assertSame(204, $oversized['status']);
        self::assertCount(2, $this->bufferLines());

        $image = $this->getImage();
        self::assertSame(200, $image['status']);
        self::assertSame('image/gif', $this->headerValue($image['headers'], 'Content-Type'));
        self::assertNotSame('', $image['body']);
        self::assertFalse(str_starts_with(realpath($this->bufferDirectory) ?: '', realpath($this->runtimeRoot.DIRECTORY_SEPARATOR.'public') ?: ''));

        $durations = [];
        for ($request = 0; $request < 20; $request++) {
            $measurement = $this->request($payload, ['Origin: https://example.test']);
            self::assertSame(204, $measurement['status']);
            $durations[] = $this->collectorDuration($measurement['headers']);
        }
        sort($durations);
        self::assertLessThan(5.0, $durations[(int) ceil(count($durations) * 0.99) - 1]);
    }

    public function test_collector_never_persists_client_supplied_ip_or_raw_user_agent_values(): void
    {
        $response = $this->request([
            'k' => 'known-site-key',
            'u' => 'https://example.test/safe',
            'e' => '203.0.113.42',
            'n' => 'RawAgent/9.9',
            'p' => [
                'ip' => '203.0.113.42',
                'ua' => 'RawAgent/9.9',
                'safe' => 'retained',
            ],
        ], [
            'Origin: https://example.test',
            'User-Agent: RawAgent/9.9',
        ]);
        self::assertSame(204, $response['status']);
        self::assertCount(1, $this->bufferLines());
        self::assertDoesNotMatchRegularExpression('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $this->bufferLines()[0]);
        self::assertStringNotContainsString('RawAgent/9.9', $this->bufferLines()[0]);

        $ipUrl = $this->request([
            'k' => 'known-site-key',
            'u' => 'https://203.0.113.42/private',
        ], ['Origin: https://example.test']);
        self::assertSame(204, $ipUrl['status']);
        self::assertCount(1, $this->bufferLines());

        $userAgentUrl = $this->request([
            'k' => 'known-site-key',
            'u' => 'https://example.test/RawAgent/9.9',
        ], [
            'Origin: https://example.test',
            'User-Agent: RawAgent/9.9',
        ]);
        self::assertSame(204, $userAgentUrl['status']);
        self::assertCount(1, $this->bufferLines());

        $ipv6 = $this->request([
            'k' => 'known-site-key',
            'u' => 'https://example.test/safe',
            'e' => '2001:db8::1',
            'p' => ['address' => '2001:db8::1'],
        ], ['Origin: https://example.test']);
        self::assertSame(204, $ipv6['status']);
        self::assertCount(2, $this->bufferLines());
        self::assertStringNotContainsString('2001:db8::1', $this->bufferLines()[1]);

        $ipv6Url = $this->request([
            'k' => 'known-site-key',
            'u' => 'https://[2001:db8::1]/private',
        ], ['Origin: https://example.test']);
        self::assertSame(204, $ipv6Url['status']);
        self::assertCount(2, $this->bufferLines());
    }

    public function test_concurrent_collectors_do_not_exceed_the_shard_ceiling(): void
    {
        $processes = [];
        $payload = (string) json_encode([
            'k' => 'known-site-key',
            'u' => 'https://example.test/concurrent',
        ], JSON_THROW_ON_ERROR);

        for ($request = 0; $request < 8; $request++) {
            $process = new Process([
                PHP_BINARY,
                $this->runtimeRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'px.php',
            ], $this->runtimeRoot, [
                'TM_BUFFER_SHARDS' => '1',
                'TM_MAX_BODY_BYTES' => '2048',
                'TM_BUFFER_MAX_MB' => '64',
                'TM_MAX_LINES_PER_MINUTE' => '1',
                'TM_RESPECT_DNT' => '0',
                'REQUEST_METHOD' => 'POST',
            ]);
            $process->setInput($payload);
            $process->start();
            $processes[] = $process;
        }

        foreach ($processes as $process) {
            $process->wait();
            self::assertSame(0, $process->getExitCode());
        }

        $lines = $this->bufferLines();
        self::assertCount(1, $lines);
        $event = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://example.test/concurrent', $event['url']);
    }

    /** @param array<string, mixed> $payload @param list<string> $headers @return array{status: int, headers: list<string>, body: string} */
    private function request(array $payload, array $headers = []): array
    {
        return $this->requestRaw((string) json_encode($payload, JSON_THROW_ON_ERROR), array_merge([
            'Content-Type: text/plain',
        ], $headers));
    }

    /** @param list<string> $headers @return array{status: int, headers: list<string>, body: string} */
    private function requestRaw(string $body, array $headers): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 2,
        ]]);
        $response = @file_get_contents('http://127.0.0.1:'.$this->port.'/px.php', false, $context);
        $responseHeaders = $http_response_header ?? [];

        preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $matches);

        return [
            'status' => (int) ($matches[1] ?? 0),
            'headers' => $responseHeaders,
            'body' => $response === false ? '' : $response,
        ];
    }

    /** @return array{status: int, headers: list<string>, body: string} */
    private function getImage(): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: image/gif\r\nReferer: https://example.test/",
            'ignore_errors' => true,
            'timeout' => 2,
        ]]);
        $response = @file_get_contents('http://127.0.0.1:'.$this->port.'/px.php?k=known-site-key&u=https%3A%2F%2Fexample.test%2F', false, $context);
        $responseHeaders = $http_response_header ?? [];
        preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $matches);

        return [
            'status' => (int) ($matches[1] ?? 0),
            'headers' => $responseHeaders,
            'body' => $response === false ? '' : $response,
        ];
    }

    /** @return list<string> */
    private function bufferLines(): array
    {
        $lines = [];

        foreach (glob($this->bufferDirectory.DIRECTORY_SEPARATOR.'*.ndjson') ?: [] as $path) {
            $contents = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_merge($lines, $contents === false ? [] : $contents);
        }

        return $lines;
    }

    /** @param list<string> $headers */
    private function collectorDuration(array $headers): float
    {
        foreach ($headers as $header) {
            if (preg_match('/^Server-Timing:\s*tm;dur=([0-9.]+)$/i', $header, $matches)) {
                return (float) $matches[1];
            }
        }

        self::fail('Collector did not return its PHP duration.');
    }

    /** @param list<string> $headers */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), strtolower($name).':')) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }

    private function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);

        if ($socket === false) {
            self::fail('Unable to allocate a local port: '.$errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr((string) $name, ':'), 1);
    }

    private function waitForServer(): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            set_error_handler(static fn (): bool => true);
            $socket = fsockopen('127.0.0.1', $this->port);
            restore_error_handler();

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(20_000);
        }

        self::fail('Collector test server did not start.');
    }

    private function removeCollectorRuntimeFiles(): void
    {
        foreach (glob($this->bufferDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            unlink($path);
        }

        if (is_dir($this->bufferDirectory)) {
            rmdir($this->bufferDirectory);
        }

        foreach ([
            $this->storage.DIRECTORY_SEPARATOR.'tm-sites.php',
            $this->storage.DIRECTORY_SEPARATOR.'tm-sites.lock',
            $this->runtimeRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'px.php',
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
