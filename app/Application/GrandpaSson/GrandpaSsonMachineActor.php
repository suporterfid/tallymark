<?php

namespace App\Application\GrandpaSson;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class GrandpaSsonMachineActor implements Authenticatable
{
    public function __construct(
        public IntrospectionResult $introspection,
        public string $tokenFingerprint,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'token_fingerprint';
    }

    public function getAuthIdentifier(): string
    {
        return $this->tokenFingerprint;
    }

    public function getAuthPasswordName(): string
    {
        return 'token';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
