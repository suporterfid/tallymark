<?php

declare(strict_types=1);

namespace App\Application\TaskConnect;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpTaskConnectTaskClient implements TaskConnectTaskClientInterface
{
    public function submit(array $task, string $idempotencyKey): TaskConnectAcceptedTask
    {
        $baseUrl = rtrim((string) config('taskconnect.base_url'), '/');
        $tenantId = (string) config('taskconnect.tenant_id');
        $environmentId = (string) config('taskconnect.environment_id');
        $token = $this->accessToken();

        if ($baseUrl === '' || $tenantId === '' || $environmentId === '') {
            throw new RuntimeException('TaskConnect is not fully configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($baseUrl.'/v1/tenants/'.$tenantId.'/environments/'.$environmentId.'/tasks', $task);

        if (! $response->successful()) {
            throw new RuntimeException('TaskConnect rejected the task submission.');
        }

        $taskId = $response->json('data.id');
        if (! is_string($taskId) || $taskId === '') {
            throw new RuntimeException('TaskConnect returned a malformed task response.');
        }

        $template = (string) config('taskconnect.run_url_template');
        $url = $template === '' ? null : str_replace('{task_id}', rawurlencode($taskId), $template);

        return new TaskConnectAcceptedTask($taskId, $url);
    }

    private function accessToken(): string
    {
        if (! (bool) config('grandpasson.outbound_enabled', false)) {
            $apiKey = (string) config('taskconnect.api_key');
            if ($apiKey === '') {
                throw new RuntimeException('TaskConnect API key is not configured.');
            }

            return $apiKey;
        }

        $baseUrl = rtrim((string) config('grandpasson.base_url'), '/');
        $clientId = (string) config('grandpasson.outbound_client_id');
        $clientSecret = (string) config('grandpasson.outbound_client_secret');
        if ($baseUrl === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('GrandpaSSOn outbound TaskConnect credentials are not configured.');
        }

        $response = Http::asForm()->timeout(10)->post($baseUrl.'/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'tasks:write',
        ]);
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || ! str_starts_with($token, 'gpat_live_')) {
            throw new RuntimeException('GrandpaSSOn outbound token request was rejected.');
        }

        return $token;
    }
}
