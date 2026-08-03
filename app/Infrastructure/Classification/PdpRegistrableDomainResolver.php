<?php

declare(strict_types=1);

namespace App\Infrastructure\Classification;

use App\Domain\Analytics\RegistrableDomainResolver;
use Pdp\Rules;

final class PdpRegistrableDomainResolver implements RegistrableDomainResolver
{
    public function __construct(private readonly Rules $rules) {}

    public function resolve(string $host): ?string
    {
        try {
            $domain = $this->rules->resolve($host)->registrableDomain()->value();
        } catch (\Throwable) {
            return null;
        }

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }
}
