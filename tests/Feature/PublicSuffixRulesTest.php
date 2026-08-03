<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Analytics\ReferrerNormalizer;
use Tests\TestCase;

final class PublicSuffixRulesTest extends TestCase
{
    public function test_the_application_binds_the_versioned_public_suffix_list_for_referrer_normalization(): void
    {
        $normalizer = $this->app->make(ReferrerNormalizer::class);

        self::assertSame(
            'example.co.in',
            $normalizer->normalize('https://example.test/', 'https://a.b.example.co.in/path', ['example.test'])->source,
        );
    }
}
