<?php

namespace App\Application\Sites;

use App\Infrastructure\Persistence\Eloquent\Site;

final class SiteKeyGenerator
{
    public function generate(): string
    {
        do {
            $siteKey = bin2hex(random_bytes(16));
        } while (Site::withoutGlobalScopes()->where('site_key', $siteKey)->exists());

        return $siteKey;
    }
}
