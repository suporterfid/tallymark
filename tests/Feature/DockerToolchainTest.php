<?php

namespace Tests\Feature;

use Tests\TestCase;

final class DockerToolchainTest extends TestCase
{
    public function test_committed_wrappers_expose_the_specified_docker_only_verbs(): void
    {
        $bashWrapper = base_path('scripts/tm.sh');
        $powerShellWrapper = base_path('scripts/tm.ps1');

        self::assertFileExists($bashWrapper);
        self::assertFileExists($powerShellWrapper);

        $expectedVerbs = ['up', 'down', 'bootstrap', 'composer', 'artisan', 'npm', 'test', 'e2e', 'load', 'release', 'deploy', 'shell', 'help'];

        foreach ($expectedVerbs as $verb) {
            self::assertStringContainsString($verb, (string) file_get_contents($bashWrapper));
            self::assertStringContainsString($verb, (string) file_get_contents($powerShellWrapper));
        }

        self::assertStringContainsString('docker compose', (string) file_get_contents($bashWrapper));
        self::assertStringContainsString("'docker', 'compose'", (string) file_get_contents($powerShellWrapper));
    }
}
