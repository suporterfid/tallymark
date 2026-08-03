<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Licensing;

use App\Domain\Licensing\LicenseAudit;
use PHPUnit\Framework\TestCase;

final class LicenseAuditTest extends TestCase
{
    public function test_it_rejects_non_permissive_and_unrecorded_dual_licences(): void
    {
        self::assertTrue(class_exists(LicenseAudit::class));
        $violations = (new LicenseAudit())->violations([
            ['name' => 'allowed/package', 'license' => ['MIT']],
            ['name' => 'dual/package', 'license' => ['BSD-3-Clause', 'GPL-3.0-only']],
            ['name' => 'forbidden/package', 'license' => ['LGPL-3.0-only']],
        ], []);

        self::assertSame([
            'dual/package requires a recorded permissive licence selection.',
            'forbidden/package has no permitted licence option.',
        ], $violations);
    }

    public function test_it_accepts_a_recorded_permissive_option_for_a_dual_licenced_package(): void
    {
        self::assertTrue(class_exists(LicenseAudit::class));
        $violations = (new LicenseAudit())->violations([
            ['name' => 'dual/package', 'license' => ['BSD-3-Clause', 'GPL-3.0-only']],
        ], ['dual/package' => 'BSD-3-Clause']);

        self::assertSame([], $violations);
    }
}
