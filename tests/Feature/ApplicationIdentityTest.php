<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ApplicationIdentityTest extends TestCase
{
    public function test_application_uses_the_tallymark_name(): void
    {
        self::assertSame('TallyMark', config('app.name'));
    }
}
