<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RepositoryPolicyTest extends TestCase
{
    public function test_hard_constraint_documents_name_the_forbidden_dependency_and_collector_boundary(): void
    {
        self::assertFileExists(base_path('CLAUDE.md'));
        self::assertFileExists(base_path('.cursor/rules/hard-constraints.mdc'));

        $claude = (string) file_get_contents(base_path('CLAUDE.md'));
        $rule = (string) file_get_contents(base_path('.cursor/rules/hard-constraints.mdc'));

        self::assertStringContainsString('matomo/device-detector', $claude);
        self::assertStringContainsString('LGPL', $claude);
        self::assertStringContainsString('vendor/autoload.php', $rule);
        self::assertStringContainsString('database connection', $rule);
        self::assertStringContainsString('alwaysApply: true', $rule);
    }
}
