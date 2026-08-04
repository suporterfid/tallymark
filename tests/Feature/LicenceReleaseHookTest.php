<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class LicenceReleaseHookTest extends TestCase
{
    public function test_both_release_wrappers_invoke_runtime_licence_and_security_audits_before_release_building(): void
    {
        $bash = (string) file_get_contents(base_path('scripts/tm.sh'));
        $powerShell = (string) file_get_contents(base_path('scripts/tm.ps1'));

        self::assertMatchesRegularExpression('/cmd_release\(\)\s*\{\s*compose run --rm app php scripts\/license-audit\.php/s', $bash);
        self::assertMatchesRegularExpression('/function Invoke-Release \{\s*Invoke-Compose @\(\'run\', \'--rm\', \'app\', \'php\', \'scripts\/license-audit\.php\'\)/s', $powerShell);
        self::assertMatchesRegularExpression('/composer_audit_with_retry\(\).*?compose run --rm app composer audit --locked --no-interaction --no-cache/s', $bash);
        self::assertMatchesRegularExpression('/cmd_release\(\).*?composer_audit_with_retry/s', $bash);
        self::assertStringContainsString("Invoke-ComposeCore @('run', '--rm', 'app', 'composer', 'audit', '--locked', '--no-interaction', '--no-cache')", $powerShell);
        self::assertMatchesRegularExpression('/function Invoke-Release \{.*?Invoke-ComposerAuditWithRetry/s', $powerShell);
    }
}
