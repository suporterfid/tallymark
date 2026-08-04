<?php

declare(strict_types=1);

namespace App\Application\TaskConnect;

use App\Domain\Shared\Clock;

final class TaskConnectDigestDelegator
{
    public function __construct(
        private readonly TaskConnectTaskClientInterface $client,
        private readonly Clock $clock,
    ) {}

    /** @param array<string,mixed> $payload */
    public function delegate(string $reportId, string $period, string $targetUrl, array $payload): ?TaskConnectAcceptedTask
    {
        if (! (bool) config('taskconnect.enabled')) {
            return null;
        }
        if ($reportId === '' || $period === '' || $targetUrl === '') {
            throw new \InvalidArgumentException('A TaskConnect digest requires a report, period, and target URL.');
        }

        return $this->client->submit([
            'name' => 'TallyMark report '.$reportId.' '.$period,
            'method' => 'POST',
            'url_or_path' => $targetUrl,
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
            'content_type' => 'application/json',
            'definition_status' => 'active',
            'schedule' => [
                'kind' => 'once',
                'timezone' => 'UTC',
                'at' => $this->clock->now()->add(new \DateInterval('PT1M'))->format(DATE_ATOM),
            ],
        ], 'tm-digest-'.hash('sha256', 'digest|'.$reportId.'|'.$period));
    }
}
