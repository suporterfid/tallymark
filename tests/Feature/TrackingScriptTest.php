<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class TrackingScriptTest extends TestCase
{
    public function test_tracker_loader_points_to_a_content_hashed_asset_and_forwards_data_attributes(): void
    {
        $loader = $this->readPublicFile('tm.js');

        self::assertMatchesRegularExpression("/tm\\.[a-f0-9]{8}\\.js/", $loader);
        self::assertStringContainsString("data-", $loader);
        self::assertStringContainsString('.currentScript', $loader);
        self::assertStringContainsString('s.src.replace', $loader);
        self::assertStringContainsString('catch', $loader);
        self::assertLessThan(1024, strlen(gzencode($loader, 9)));
    }

    public function test_hashed_tracker_is_small_private_and_uses_the_required_delivery_fallbacks(): void
    {
        [$filename, $asset] = $this->readHashedAsset();

        self::assertLessThan(1024, strlen(gzencode($asset, 9)));
        self::assertSame('tm.'.substr(hash('sha256', $asset), 0, 8).'.js', $filename);
        self::assertStringContainsString('navigator.sendBeacon', $asset);
        self::assertStringContainsString('fetch(', $asset);
        self::assertStringContainsString('keepalive', $asset);
        self::assertStringContainsString('new Image', $asset);
        self::assertStringContainsString('window.tallymark', $asset);
        self::assertStringContainsString('doNotTrack', $asset);
        self::assertStringContainsString('globalPrivacyControl', $asset);
        self::assertStringContainsString('localhost', $asset);
        self::assertStringContainsString('history.pushState', $asset);
        self::assertStringContainsString('s.src.replace', $asset);
        self::assertStringContainsString('new URL', $asset);
        self::assertStringContainsString('searchParams', $asset);
        self::assertStringContainsString('u(location.href)', $asset);
        self::assertStringContainsString('169\\.254', $asset);
        self::assertStringContainsString('f[cd]', $asset);
        self::assertStringContainsString('fe[89ab]', $asset);
        self::assertStringNotContainsString("'&p='", $asset);
        self::assertStringContainsString('.catch(function(){g(', $asset);

        foreach (['document.cookie', 'localStorage', 'sessionStorage', 'canvas', 'WebGL', 'fonts', 'hardware'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $asset);
        }
    }

    public function test_hashed_tracker_cache_and_csp_installation_contract_are_documented(): void
    {
        $htaccess = $this->readPublicFile('.htaccess');
        $documentation = file_get_contents(base_path('docs/tracking.md'));

        self::assertIsString($documentation);
        self::assertStringContainsString('max-age=31536000', $htaccess);
        self::assertStringContainsString('immutable', $htaccess);
        self::assertStringContainsString('<FilesMatch "^tm\\.js$">', $htaccess);
        self::assertStringContainsString("script-src 'self'", $documentation);
        self::assertStringContainsString("connect-src 'self'", $documentation);
        self::assertMatchesRegularExpression('/<script defer src="[^\"]+" data-site="[^\"]+"><\\/script>/', $documentation);
    }

    /** @return array{string, string} */
    private function readHashedAsset(): array
    {
        $assets = glob(base_path('public/tm.[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9].js'));

        self::assertIsArray($assets);
        self::assertCount(1, $assets);

        $filename = basename($assets[0]);

        return [$filename, $this->readPublicFile($filename)];
    }

    private function readPublicFile(string $filename): string
    {
        $contents = file_get_contents(base_path('public/'.$filename));

        self::assertIsString($contents);

        return $contents;
    }
}
