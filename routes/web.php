<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\PublicStatusPageController;
use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/dashboard', '/monitors');

    Route::resource('monitors', MonitorController::class);
    Route::post('/monitors/{monitor}/pause', [MonitorController::class, 'pause'])->name('monitors.pause');
    Route::post('/monitors/{monitor}/resume', [MonitorController::class, 'resume'])->name('monitors.resume');

    Route::resource('channels', NotificationChannelController::class)->except(['show']);
    Route::post('/channels/{channel}/test', [NotificationChannelController::class, 'test'])->name('channels.test');

    Route::resource('status-pages', StatusPageController::class)->except(['show']);
});

// Public status page (no auth)
Route::get('/status/{slug}', [PublicStatusPageController::class, 'show'])->name('status.show');
