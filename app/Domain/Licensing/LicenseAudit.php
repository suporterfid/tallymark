<?php

declare(strict_types=1);

namespace App\Domain\Licensing;

final class LicenseAudit
{
    private const ALLOWED = ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC'];

    /** @param list<array{name: string, license?: list<string>}> $packages @param array<string, string> $selections @return list<string> */
    public function violations(array $packages, array $selections): array
    {
        $violations = [];

        foreach ($packages as $package) {
            $licenses = $package['license'] ?? [];
            $permitted = array_values(array_intersect($licenses, self::ALLOWED));
            if ($permitted === []) {
                $violations[] = $package['name'].' has no permitted licence option.';
                continue;
            }

            if (count($licenses) > 1 && (! isset($selections[$package['name']]) || ! in_array($selections[$package['name']], $permitted, true))) {
                $violations[] = $package['name'].' requires a recorded permissive licence selection.';
            }
        }

        return $violations;
    }
}
