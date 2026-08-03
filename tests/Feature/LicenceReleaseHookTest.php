<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class LicenceReleaseHookTest extends TestCase
{
    public function test_both_release_wrappers_invoke_the_runtime_licence_audit_before_release_building(): void
    {
        $bash = (string) file_get_contents(base_path('scripts/tm.sh'));
        $powerShell = (string) file_get_contents(base_path('scripts/tm.ps1'));

        self::assertMatchesRegularExpression('/cmd_release\(\)\s*\{\s*compose run --rm app php scripts\/license-audit\.php/s', $bash);
        self::assertMatchesRegularExpression('/function Invoke-Release \{\s*Invoke-Compose @\(\'run\', \'--rm\', \'app\', \'php\', \'scripts\/license-audit\.php\'\)/s', $powerShell);
    }
}
