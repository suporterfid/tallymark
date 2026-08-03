<?php

namespace Tests\Feature;

use App\Domain\Licensing\LicenseAudit;
use Tests\TestCase;

final class LicensingPolicyTest extends TestCase
{
    public function test_project_and_dual_licensed_runtime_dependencies_have_a_permissive_licensing_record(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true, 512, JSON_THROW_ON_ERROR);
        $packages = collect($lock['packages'])->keyBy('name');

        self::assertSame('MIT', $composer['license']);
        self::assertStringStartsWith('MIT License', (string) file_get_contents(base_path('LICENSE')));

        foreach (['nette/schema', 'nette/utils'] as $packageName) {
            self::assertContains('BSD-3-Clause', $packages[$packageName]['license']);
        }

        self::assertFileExists(base_path('docs/security/dependency-audit.md'));
        $audit = (string) file_get_contents(base_path('docs/security/dependency-audit.md'));
        self::assertStringContainsString('New BSD', $audit);
        self::assertStringContainsString('nette/schema', $audit);
        self::assertStringContainsString('nette/utils', $audit);
    }

    public function test_runtime_lock_file_has_no_licence_audit_violations(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true, 512, JSON_THROW_ON_ERROR);
        $selections = require base_path('config/licence-selections.php');

        self::assertSame([], (new LicenseAudit())->violations($lock['packages'], $selections));
    }
}
