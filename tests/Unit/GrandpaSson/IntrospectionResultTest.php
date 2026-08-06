<?php

namespace Tests\Unit\GrandpaSson;

use App\Application\GrandpaSson\IntrospectionResult;
use PHPUnit\Framework\TestCase;

final class IntrospectionResultTest extends TestCase
{
    public function test_audience_includes_the_raw_tenant_public_id(): void
    {
        $result = new IntrospectionResult(active: true, audiences: ['ten_analytics']);

        self::assertTrue($result->audienceIncludes('ten_analytics'));
        self::assertFalse($result->audienceIncludes('ten_other'));
    }

    public function test_audience_includes_the_workspace_prefixed_tenant_public_id(): void
    {
        $result = new IntrospectionResult(active: true, audiences: ['workspace/ten_analytics']);

        self::assertTrue($result->audienceIncludes('ten_analytics'));
        self::assertFalse($result->audienceIncludes('ten_other'));
    }
}
