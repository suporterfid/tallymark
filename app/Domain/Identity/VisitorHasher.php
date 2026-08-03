<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use InvalidArgumentException;

final class VisitorHasher
{
    public function hash(string $salt, int $siteId, string $clientIp, string $userAgent): string
    {
        if (strlen($salt) < 64 || ! ctype_xdigit($salt)) {
            throw new InvalidArgumentException('Visitor salts must contain at least 256 bits of hexadecimal entropy.');
        }

        return substr(hash('sha256', $salt."\0".$siteId."\0".$clientIp."\0".$userAgent), 0, 16);
    }
}
