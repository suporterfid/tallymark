<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\ReferrerNormalizer;
use App\Infrastructure\Classification\PdpRegistrableDomainResolver;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

final class ReferrerNormalizerTest extends TestCase
{
    public function test_utm_source_takes_precedence_over_a_referrer_domain(): void
    {
        self::assertTrue(class_exists(ReferrerNormalizer::class));
        $result = (new ReferrerNormalizer($this->rules()))->normalize(
            'https://example.test/pricing?utm_source=newsletter',
            'https://search.example.org/results',
            ['example.test'],
        );

        self::assertSame('newsletter', $result->source);
        self::assertFalse($result->spam);
    }

    public function test_self_referrals_and_operator_spam_domains_are_direct(): void
    {
        self::assertTrue(class_exists(ReferrerNormalizer::class));
        $normalizer = new ReferrerNormalizer($this->rules(), ['spam.example']);

        self::assertSame('direct', $normalizer->normalize('https://example.test/', 'https://www.example.test/path', ['example.test', 'www.example.test'])->source);
        $spam = $normalizer->normalize('https://example.test/', 'https://click.spam.example/path', ['example.test']);
        self::assertSame('direct', $spam->source);
        self::assertTrue($spam->spam);
    }

    public function test_it_keeps_the_registrable_domain_for_a_multi_label_public_suffix(): void
    {
        $result = (new ReferrerNormalizer($this->rules()))->normalize('https://example.test/', 'https://a.b.example.co.in/path', ['example.test']);

        self::assertSame('example.co.in', $result->source);
    }

    private function rules(): PdpRegistrableDomainResolver
    {
        return new PdpRegistrableDomainResolver(Rules::fromString("// ===BEGIN ICANN DOMAINS===\ncom\nin\nco.in\ntest\n// ===END ICANN DOMAINS===\n"));
    }
}
