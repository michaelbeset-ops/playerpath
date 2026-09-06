<?php

use App\Http\Controllers\Clients\ClientDirectoryController;
use App\Http\Controllers\Goals\GoalController;
use App\Http\Controllers\Groups\GroupController;
use App\Http\Controllers\Media\PhotoController;
use App\Http\Controllers\Players\GuardianController;
use App\Http\Controllers\Players\PlayerCardController;
use App\Http\Controllers\Players\PlayerController;
use App\Http\Controllers\Players\PlayerProgressController;
use App\Http\Controllers\Players\SharedCardController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\TrainerController;
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

    // Klanten: de spelers en hun ouders. Twee lijsten, want je zoekt zelden
    // een speler en een ouder tegelijk.
    Route::get('clients', [ClientDirectoryController::class, 'players'])->name('clients.players');
    Route::get('clients/guardians', [ClientDirectoryController::class, 'guardians'])->name('clients.guardians');

    // Personeel hoort bij het bedrijf, niet bij de klanten.
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff/trainers', [TrainerController::class, 'store'])->name('trainers.store');
    Route::delete('staff/trainers/{user}', [TrainerController::class, 'destroy'])->name('trainers.destroy');

    // Profielfoto's. Wie wat mag staat in de policies: update op de speler
    // (de eigenaar) en update op de gebruiker (jezelf, of de eigenaar).
    Route::post('players/{player}/photo', [PhotoController::class, 'storePlayer'])->name('players.photo.store');
    Route::delete('players/{player}/photo', [PhotoController::class, 'destroyPlayer'])->name('players.photo.destroy');
    Route::post('users/{user}/photo', [PhotoController::class, 'storeUser'])->name('users.photo.store');
    Route::delete('users/{user}/photo', [PhotoController::class, 'destroyUser'])->name('users.photo.destroy');

    // Oude adressen blijven werken: /users en /players stonden in bladwijzers
    // en verwijzingen voordat dit Klanten heette.
    Route::get('users', fn () => redirect()->route('clients.players'))->name('users.index');
    Route::get('players', fn () => redirect()->route('clients.players'))->name('players.index');
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
