<?php

namespace App\Application\Sites;

interface SiteMapWriter
{
    public function regenerate(): void;
}
