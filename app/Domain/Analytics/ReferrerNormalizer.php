<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

final class ReferrerNormalizer
{
    /** @param list<string> $spamDomains */
    public function __construct(private readonly RegistrableDomainResolver $domains, private readonly array $spamDomains = []) {}

    /** @param list<string> $siteHosts */
    public function normalize(string $url, ?string $referrer, array $siteHosts): ReferrerClassification
    {
        $source = $this->utmSource($url);
        if ($source !== null) {
            return new ReferrerClassification($source, false);
        }

        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;
        if (! is_string($host) || $host === '') {
            return new ReferrerClassification('direct', false);
        }

        $domain = $this->registrableDomain($host);
        if ($domain === null) {
            return new ReferrerClassification('direct', false);
        }
        foreach ($siteHosts as $siteHost) {
            if ($domain === $this->registrableDomain($siteHost)) {
                return new ReferrerClassification('direct', false);
            }
        }
        foreach ($this->spamDomains as $spamDomain) {
            $spamDomain = $this->registrableDomain($spamDomain);
            if ($spamDomain !== null && $domain === $spamDomain) {
                return new ReferrerClassification('direct', true);
            }
        }

        return new ReferrerClassification($domain, false);
    }

    private function utmSource(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $parameters);
        $source = $parameters['utm_source'] ?? null;

        return is_string($source) && $source !== '' ? strtolower(substr($source, 0, 128)) : null;
    }

    private function registrableDomain(string $host): ?string
    {
        return $this->domains->resolve($host);
    }
}
