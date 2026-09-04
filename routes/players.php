<?php

use App\Http\Controllers\Groups\GroupController;
use App\Http\Controllers\Players\GuardianController;
use App\Http\Controllers\Players\PlayerCardController;
use App\Http\Controllers\Players\PlayerController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Fase 2: het spoor rapport -> spelerskaart.
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('players/{player}/reports/create', [ReportController::class, 'create'])->name('reports.create');
    Route::post('players/{player}/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('players/{player}/card', [PlayerCardController::class, 'show'])->name('players.card');

    // Fase 3: het ledenbestand.
    Route::resource('players', PlayerController::class);
    Route::resource('groups', GroupController::class)->except('show');

    Route::post('players/{player}/guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::post('players/{player}/guardians/invite', [GuardianController::class, 'invite'])->name('guardians.invite');
    Route::delete('players/{player}/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');
});
