<?php

namespace App\Application\GrandpaSson;

final readonly class IntrospectionResult
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $audiences
     */
    public function __construct(
        public bool $active,
        public array $scopes = [],
        public array $audiences = [],
        public ?string $clientId = null,
        public ?string $subject = null,
        public ?int $expiresAtUnix = null,
    ) {}

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function audienceIncludes(string $tenantPublicId): bool
    {
        return in_array($tenantPublicId, $this->audiences, true)
            || in_array('workspace/'.$tenantPublicId, $this->audiences, true);
    }
}
