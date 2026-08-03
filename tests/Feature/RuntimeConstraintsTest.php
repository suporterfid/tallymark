<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RuntimeConstraintsTest extends TestCase
{
    public function test_composer_scripts_do_not_start_persistent_processes(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $scripts = $composer['scripts'] ?? [];
        $encodedScripts = json_encode($scripts, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('dev', $scripts);
        self::assertArrayNotHasKey('setup', $scripts);
        self::assertStringNotContainsString('queue:listen', $encodedScripts);
        self::assertStringNotContainsString('queue:work', $encodedScripts);
        self::assertStringNotContainsString('pail --timeout=0', $encodedScripts);
    }
}
