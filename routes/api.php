<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant.context'])->group(function (): void {
    Route::get('/v1/tenants/{tenant}/audit-logs', [AuditLogController::class, 'index']);
});
