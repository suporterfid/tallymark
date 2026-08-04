<?php

declare(strict_types=1);

namespace App\Application\TaskConnect;

final readonly class TaskConnectAcceptedTask
{
    public function __construct(public string $id, public ?string $url) {}
}
