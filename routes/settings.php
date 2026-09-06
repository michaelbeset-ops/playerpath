<?php

use App\Http\Controllers\Settings\DashboardPreferenceController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.settings.update');

    // Zelf bepalen wat er op je dashboard staat. Alleen voor wie een
    // schooldashboard heeft; een ouder ziet de kaart van zijn kind.
    Route::get('settings/dashboard', [DashboardPreferenceController::class, 'edit'])->name('dashboard.preferences.edit');
    Route::patch('settings/dashboard', [DashboardPreferenceController::class, 'update'])->name('dashboard.preferences.update');

    // Fase 10: welke mail wil je ontvangen. Meldingen in de app staan altijd aan.
    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');
});
