<?php

namespace App\Application\GrandpaSson;

interface SessionExchangeClientInterface
{
    public function exchange(string $code, ?string $tenant = null): GrandpaSsonSession;
}
