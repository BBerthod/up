<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\IngestSourceApiController;
use App\Http\Controllers\Api\MonitorApiController;
use App\Http\Controllers\Api\NotificationChannelApiController;
use App\Http\Controllers\Api\SearchApiController;
use App\Http\Controllers\Api\StatusPageApiController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// Public status page API (no auth)
Route::get('/status-pages/public/{slug}', [StatusPageApiController::class, 'publicShow'])->name('api.status-pages.public');

// Event ingestion (token-based auth, no Bearer)
Route::post('/ingest/{token}', [IngestController::class, 'receive'])
    ->name('api.ingest.receive')
    ->middleware('throttle:100,1');

// Authenticated API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/search', SearchApiController::class)->name('api.search');
    Route::apiResource('monitors', MonitorApiController::class)->names('api.monitors');
    Route::post('/monitors/{monitor}/pause', [MonitorApiController::class, 'pause'])->name('api.monitors.pause');
    Route::post('/monitors/{monitor}/resume', [MonitorApiController::class, 'resume'])->name('api.monitors.resume');
    Route::get('/monitors/{monitor}/checks', [MonitorApiController::class, 'checks'])->name('api.monitors.checks');

    Route::apiResource('notification-channels', NotificationChannelApiController::class)->names('api.notification-channels');

    Route::apiResource('status-pages', StatusPageApiController::class)->names('api.status-pages');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::apiResource('ingest-sources', IngestSourceApiController::class)->names('api.ingest-sources');
    Route::post('/ingest-sources/{ingestSource}/rotate-token', [IngestSourceApiController::class, 'rotateToken'])->name('api.ingest-sources.rotate-token');
    Route::get('/ingest-sources/{ingestSource}/events', [IngestSourceApiController::class, 'events'])->name('api.ingest-sources.events');
});
