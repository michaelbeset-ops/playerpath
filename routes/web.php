<?php

use App\Http\Controllers\Branding\BrandingController;
use App\Http\Controllers\Communication\AnnouncementController;
use App\Http\Controllers\Communication\BirthdayGreetingController;
use App\Http\Controllers\Dashboard\AccountabilityController;
use App\Http\Controllers\Dashboard\LayoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Players\PlayerDataController;
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
    // Je eigen indeling van het dashboard. Alles via de server: een indeling
    // die alleen in de browser bestaat staat op je telefoon anders dan op je
    // laptop, en dat is niet wat "mijn indeling" hoort te betekenen.
    Route::patch('dashboard/indeling', [LayoutController::class, 'update'])->name('dashboard.layout.update');
    Route::delete('dashboard/indeling', [LayoutController::class, 'destroy'])->name('dashboard.layout.destroy');

    // Het aandacht-blok wegklikken. Komt terug zodra er iets verandert.
    Route::post('dashboard/aandacht/gezien', [LayoutController::class, 'dismissAttention'])->name('dashboard.attention.dismiss');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

    // Overzichten exporteren (eigenaar). Zie Support/Exports.
    Route::middleware('feature:exports')->group(function () {
        Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('exports/{key}', [ExportController::class, 'download'])->name('exports.download');
    });

    // Wat de school naar buiten kan laten zien. Alleen de eigenaar.
    Route::get('verantwoording', AccountabilityController::class)->name('accountability');

    // Fase 11: eigen logo en kleur. Alleen de eigenaar.
    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('branding', [BrandingController::class, 'update'])->name('branding.update');

    // Fase 10: mededelingen van de school aan ouders en spelers.
    Route::middleware('feature:mededelingen')->group(function () {
        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

        // De automatische verjaardagsfelicitatie. Alleen de eigenaar: dit gaat
        // namens de school de deur uit.
        Route::get('announcements/verjaardagen', [BirthdayGreetingController::class, 'edit'])->name('birthdays.edit');
        Route::patch('announcements/verjaardagen', [BirthdayGreetingController::class, 'update'])->name('birthdays.update');
    });

    // Inzageverzoek: alles wat de school over één speler bewaart, in één
    // werkmap. Staat op de pagina van die speler, want daar stelt een ouder de
    // vraag. Alleen de eigenaar.
    Route::get('players/{player}/gegevens', PlayerDataController::class)->name('players.data');
});

require __DIR__.'/players.php';
require __DIR__.'/trainings.php';
require __DIR__.'/billing.php';
require __DIR__.'/enrollments.php';
require __DIR__.'/settings.php';
require __DIR__.'/platform.php';
