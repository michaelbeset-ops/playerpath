<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Onboarding\SendInvitation;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Trainers uitnodigen en verwijderen.
 *
 * Net als bij ouders zet de eigenaar geen wachtwoord en maakt hij geen account
 * aan: de trainer krijgt een welkomstmail uit naam van de school, activeert
 * zijn account en kiest zelf een wachtwoord. Zo kent niemand andermans
 * wachtwoord.
 */
class TrainerController extends Controller
{
    public function store(Request $request, SendInvitation $uitnodigen): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [
            'email.unique' => 'Er bestaat al een account met dit e-mailadres.',
        ], [
            'name' => 'De naam',
            'email' => 'Het e-mailadres',
        ]);

        $uitnodigen->handle(
            $request->user()->school,
            $validated['name'],
            $validated['email'],
            Role::Trainer->value,
            $request->user(),
        );

        return back()->with(
            'status',
            "{$validated['name']} krijgt een welkomstmail om het trainersaccount te activeren."
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        abort_if($user->id === $request->user()->id, 422, 'Je kunt je eigen account niet verwijderen.');
        abort_if($user->isEigenaar(), 422, 'De eigenaar van de school kun je niet verwijderen.');

        // Zijn rapporten blijven bestaan met een lege trainer: die historie
        // hoort bij de speler, niet bij de trainer. Zie de migratie
        // keep_reports_when_a_trainer_leaves.
        $naam = $user->name;
        $user->delete();

        return back()->with('status', "Het account van {$naam} is verwijderd. Zijn rapporten blijven bewaard.");
    }
}
