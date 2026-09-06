<?php

use App\Http\Controllers\Branding\BrandingController;
use App\Http\Controllers\Communication\AnnouncementController;
use App\Http\Controllers\Dashboard\AccountabilityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Privacy\PrivacyController;
use App\Http\Controllers\Pwa\ManifestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

/*
 * De PWA-laag. Bewust zonder inlog: een manifest en een offline-pagina moeten
 * ook op te halen zijn als de sessie verlopen is, anders is de app niet
 * installeerbaar.
 */
Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');
Route::get('offline', fn () => Inertia::render('Offline'))->name('offline');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

    // Overzichten exporteren (eigenaar). Zie Support/Exports.
    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{key}', [ExportController::class, 'download'])->name('exports.download');

    // Wat de school naar buiten kan laten zien. Alleen de eigenaar.
    Route::get('verantwoording', AccountabilityController::class)->name('accountability');

    // Fase 11: eigen logo en kleur. Alleen de eigenaar.
    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('branding', [BrandingController::class, 'update'])->name('branding.update');

    // Fase 10: mededelingen van de school aan ouders en spelers.
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

    // Fase 8: bewaartermijn, inzage en verwijderen (AVG). Alleen de eigenaar.
    Route::get('privacy', [PrivacyController::class, 'index'])->name('privacy.index');
    Route::patch('privacy', [PrivacyController::class, 'update'])->name('privacy.update');
    Route::get('players/{player}/gegevens', [PrivacyController::class, 'download'])->name('privacy.player-data');
    Route::delete('privacy/players/{player}', [PrivacyController::class, 'erase'])->name('privacy.erase');
});

require __DIR__.'/players.php';
require __DIR__.'/trainings.php';
require __DIR__.'/billing.php';
require __DIR__.'/enrollments.php';
require __DIR__.'/settings.php';
require __DIR__.'/platform.php';
