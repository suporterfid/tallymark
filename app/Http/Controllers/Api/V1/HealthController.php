<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\GrandpaSson\GrandpaSsonMachineActor;
use App\Domain\Shared\Clock;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class HealthController extends Controller
{
    public function __construct(private readonly Clock $clock) {}

    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        $machineActor = $request->attributes->get('grandpasson.machine_actor');
        abort_unless($machineActor instanceof GrandpaSsonMachineActor || auth()->user()?->isPlatformAdmin(), 403);

        $heartbeats = DB::table('system_heartbeats')
            ->whereIn('name', ['analytics:ingest', 'analytics:rollup'])
            ->get()
            ->keyBy('name');
        $bufferDirectory = storage_path('tm-buffer');
        $shedFile = $bufferDirectory.DIRECTORY_SEPARATOR.'tm-shed.count';

        return response()->json(['data' => [
            'ingest' => $this->heartbeatState($heartbeats->get('analytics:ingest')),
            'rollup' => $this->heartbeatState($heartbeats->get('analytics:rollup')),
            'buffer_depth' => count(glob($bufferDirectory.DIRECTORY_SEPARATOR.'*.ndjson') ?: []),
            'shed_events' => intdiv(is_file($shedFile) ? (int) (@filesize($shedFile) ?: 0) : 0, 2),
        ]]);
    }

    /** @return array{fresh:bool,status:?string,last_seen_at:?string,message:?string} */
    private function heartbeatState(?object $heartbeat): array
    {
        $lastSeen = $heartbeat?->last_seen_at === null ? null : new \DateTimeImmutable((string) $heartbeat->last_seen_at);
        $freshAfter = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->sub(new \DateInterval('PT3M'));

        return [
            'fresh' => $heartbeat?->status === 'healthy' && $lastSeen !== null && $lastSeen >= $freshAfter,
            'status' => is_string($heartbeat?->status) ? $heartbeat->status : null,
            'last_seen_at' => $lastSeen?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'message' => is_string($heartbeat?->message) ? $heartbeat->message : null,
        ];
    }
}
