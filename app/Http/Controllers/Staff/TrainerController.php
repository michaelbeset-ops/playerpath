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
            // Neutraal: het adres kan bij een andere school horen, en dat gaat
            // deze school niets aan.
            'email.unique' => 'Dit e-mailadres is al in gebruik.',
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

    /**
     * Een trainer zonder inlog (uit een import) een inlog geven.
     *
     * Hetzelfde als uitnodigen, maar de uitnodiging hoort bij zijn bestaande
     * account: bij activatie krijgt dat account het adres en een wachtwoord
     * dat hij zelf kiest. Zijn trainingen en rapporten blijven van hem.
     */
    public function login(Request $request, User $user, SendInvitation $uitnodigen): RedirectResponse
    {
        $this->authorize('create', User::class);
        $this->authorize('update', $user);

        abort_unless($user->isTrainer() && $user->belongsToSameSchool($request->user()), 404);

        if ($user->email !== null) {
            return back()->with('error', "{$user->name} heeft al een inlog.");
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [
            'email.unique' => 'Dit e-mailadres is al in gebruik.',
        ], [
            'email' => 'Het e-mailadres',
        ]);

        $uitnodigen->handle(
            $request->user()->school,
            $user->name,
            $validated['email'],
            Role::Trainer->value,
            $request->user(),
            account: $user,
        );

        return back()->with('status', "{$user->name} krijgt een welkomstmail om een wachtwoord te kiezen.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Je kunt je eigen account niet verwijderen.']);
        }

        if ($user->isEigenaar()) {
            return back()->withErrors(['user' => 'De eigenaar van de school kun je niet verwijderen.']);
        }

        // Deze route is voor trainers; ouders en spelers gaan via hun eigen weg.
        abort_unless($user->isTrainer(), 404);

        // Zijn rapporten blijven bestaan met een lege trainer: die historie
        // hoort bij de speler, niet bij de trainer. Zie de migratie
        // keep_reports_when_a_trainer_leaves.
        $naam = $user->name;
        $user->delete();

        return back()->with('status', "Het account van {$naam} is verwijderd. Zijn rapporten blijven bewaard.");
    }
}
