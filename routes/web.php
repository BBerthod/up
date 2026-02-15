<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicStatusPageController;
use App\Http\Controllers\StatusPageController;
use App\Http\Controllers\TeamSettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/settings', [TeamSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/team', [TeamSettingsController::class, 'updateTeam'])->name('settings.team.update');
    Route::post('/settings/tokens', [TeamSettingsController::class, 'createToken'])->name('settings.tokens.store');
    Route::delete('/settings/tokens/{tokenId}', [TeamSettingsController::class, 'deleteToken'])->name('settings.tokens.destroy');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/export', [IncidentController::class, 'export'])->name('incidents.export');

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
