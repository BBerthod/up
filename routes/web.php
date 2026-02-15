<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\PublicStatusPageController;
use App\Http\Controllers\StatusPageController;
use App\Http\Controllers\TeamSettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirectToProvider'])->name('oauth.redirect');
    Route::get('/auth/{provider}/callback', [OAuthController::class, 'handleProviderCallback'])->name('oauth.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/settings', [TeamSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/team', [TeamSettingsController::class, 'updateTeam'])->name('settings.team.update');
    Route::post('/settings/tokens', [TeamSettingsController::class, 'createToken'])->name('settings.tokens.store');
    Route::delete('/settings/tokens/{tokenId}', [TeamSettingsController::class, 'deleteToken'])->name('settings.tokens.destroy');

    Route::resource('monitors', MonitorController::class);
    Route::post('/monitors/{monitor}/pause', [MonitorController::class, 'pause'])->name('monitors.pause');
    Route::post('/monitors/{monitor}/resume', [MonitorController::class, 'resume'])->name('monitors.resume');

    Route::resource('channels', NotificationChannelController::class)->except(['show']);
    Route::post('/channels/{channel}/test', [NotificationChannelController::class, 'test'])->name('channels.test');

    Route::resource('status-pages', StatusPageController::class)->except(['show']);

    // Admin routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class);
    });
});

// Public routes (no auth)
Route::get('/status/{slug}', [PublicStatusPageController::class, 'show'])->name('status.show');
Route::get('/badge/{hash}.svg', BadgeController::class)->name('badge');
