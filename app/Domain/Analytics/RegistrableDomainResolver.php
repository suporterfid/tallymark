<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

interface RegistrableDomainResolver
{
    public function resolve(string $host): ?string;
}
