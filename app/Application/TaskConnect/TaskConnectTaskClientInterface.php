<?php

declare(strict_types=1);

namespace App\Application\TaskConnect;

interface TaskConnectTaskClientInterface
{
    /** @param array<string,mixed> $task */
    public function submit(array $task, string $idempotencyKey): TaskConnectAcceptedTask;
}
