<?php

namespace App\Application\GrandpaSson;

final readonly class GrandpaSsonSession
{
    /**
     * @param  list<array{id: string, slug?: string, role?: string}>  $tenants
     * @param  list<string>  $groups
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $subjectId,
        public string $email,
        public string $name,
        public ?string $identityProvider,
        public ?string $tenantId,
        public array $tenants,
        public array $groups,
        public array $scopes,
    ) {}
}
