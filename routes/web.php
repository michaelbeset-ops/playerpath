<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

    // Overzichten exporteren (eigenaar). Zie Support/Exports.
    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{key}', [ExportController::class, 'download'])->name('exports.download');
});

require __DIR__.'/players.php';
require __DIR__.'/trainings.php';
require __DIR__.'/billing.php';
require __DIR__.'/settings.php';
