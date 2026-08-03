<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use Illuminate\Http\JsonResponse;

final class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AuditLog::query()->latest('id')->get(),
        ]);
    }
}
