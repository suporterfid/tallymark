<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\UserLocaleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'locale.from_user'])->group(function (): void {
    Route::get('/v1/me', MeController::class);
    Route::patch('/v1/me/locale', UserLocaleController::class);
});

Route::middleware(['auth', 'locale.from_user', 'tenant.context'])->group(function (): void {
    Route::get('/v1/tenants/{tenant}/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/v1/tenants/{tenant}/sites', [SiteController::class, 'index']);
    Route::get('/v1/tenants/{tenant}/sites/{siteId}/report', [ReportController::class, 'show']);
    Route::get('/v1/tenants/{tenant}/sites/{siteId}/realtime', [ReportController::class, 'realtime']);
    Route::post('/v1/tenants/{tenant}/sites', [SiteController::class, 'store']);
    Route::patch('/v1/tenants/{tenant}/sites/{siteId}', [SiteController::class, 'update']);
    Route::delete('/v1/tenants/{tenant}/sites/{siteId}', [SiteController::class, 'destroy']);
    Route::post('/v1/tenants/{tenant}/sites/{siteId}/hosts', [SiteController::class, 'addHost']);
    Route::delete('/v1/tenants/{tenant}/sites/{siteId}/hosts/{hostId}', [SiteController::class, 'removeHost']);
    Route::post('/v1/tenants/{tenant}/sites/{siteId}/rotate-key', [SiteController::class, 'rotateKey']);
});
