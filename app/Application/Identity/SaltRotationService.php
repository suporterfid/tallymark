<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Sites\SiteMapWriter;
use App\Infrastructure\Persistence\Eloquent\Salt;

final class SaltRotationService
{
    public function __construct(
        private readonly CurrentSaltProvider $currentSaltProvider,
        private readonly SiteMapWriter $siteMapWriter,
    ) {}

    public function maintain(): Salt
    {
        $salt = $this->currentSaltProvider->current();
        $this->siteMapWriter->regenerate();

        return $salt;
    }
}
