<?php

use App\Http\Controllers\Goals\GoalController;
use App\Http\Controllers\Groups\GroupController;
use App\Http\Controllers\Players\GuardianController;
use App\Http\Controllers\Players\PlayerCardController;
use App\Http\Controllers\Players\PlayerController;
use App\Http\Controllers\Players\PlayerProgressController;
use App\Http\Controllers\Players\SharedCardController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Users\TrainerController;
use App\Http\Controllers\Users\UserDirectoryController;
use App\Http\Middleware\PreventSearchIndexing;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // De hele ontwikkelingslaag achter één feature: rapport, kaart, voortgang
    // en doelen horen bij elkaar. Los aan te zetten zou een school opleveren
    // met rapporten maar zonder kaart, en dat is niemand.
    Route::middleware('feature:ontwikkeling')->group(function () {
        // Fase 2: het spoor rapport -> spelerskaart.
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('players/{player}/reports/create', [ReportController::class, 'create'])->name('reports.create');
        Route::post('players/{player}/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('players/{player}/card', [PlayerCardController::class, 'show'])->name('players.card');

        // Fase 5: groei over de tijd en de tijdlijn.
        Route::get('players/{player}/progress', [PlayerProgressController::class, 'show'])->name('players.progress');

        // Fase 7: ontwikkelingsdoelen.
        Route::post('players/{player}/goals', [GoalController::class, 'store'])->name('goals.store');
        Route::delete('goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');
    });

    // De deel-link aan- en uitzetten. De publieke pagina zelf staat hieronder,
    // bewust buiten de auth-groep.
    Route::post('players/{player}/share', [SharedCardController::class, 'store'])->name('players.share');
    Route::delete('players/{player}/share', [SharedCardController::class, 'destroy'])->name('players.unshare');

    // Gebruikers: spelers, trainers en ouders op één scherm met tabbladen.
    Route::get('users', [UserDirectoryController::class, 'index'])->name('users.index');
    Route::post('users/trainers', [TrainerController::class, 'store'])->name('trainers.store');
    Route::delete('users/trainers/{user}', [TrainerController::class, 'destroy'])->name('trainers.destroy');

    // Het spelersoverzicht woont nu onder Gebruikers; oude links blijven werken.
    Route::get('players', fn () => redirect()->route('users.index'))->name('players.index');
    Route::resource('players', PlayerController::class)->except(['index']);
    Route::resource('groups', GroupController::class)->except('show');

    Route::post('players/{player}/guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::post('players/{player}/guardians/invite', [GuardianController::class, 'invite'])->name('guardians.invite');
    Route::delete('players/{player}/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');
});

// De enige route zonder inlog. Zie SharedCardController voor waarom dat kan.
// Het token van 48 tekens is niet te raden, maar een limiet houdt iemand die
// het toch probeert buiten de deur en scheelt onnodige belasting.
Route::get('kaart/{token}', [SharedCardController::class, 'show'])
    ->middleware([PreventSearchIndexing::class, 'throttle:60,1'])
    ->name('players.shared')
    ->where('token', '[A-Za-z0-9]{48}');
