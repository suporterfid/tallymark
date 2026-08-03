<?php

namespace App\Domain\Shared;

use Symfony\Component\Uid\Ulid;

final class PublicId
{
    public static function generate(?string $prefix = null): string
    {
        $id = (string) new Ulid();

        if ($prefix === null || $prefix === '') {
            return $id;
        }

        return str_ends_with($prefix, '_') ? $prefix.$id : $prefix.'_'.$id;
    }
}
