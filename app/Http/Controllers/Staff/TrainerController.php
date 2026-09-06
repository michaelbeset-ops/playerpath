<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Trainers uitnodigen en verwijderen.
 *
 * Net als bij ouders zet de eigenaar geen wachtwoord: de trainer krijgt een
 * e-mail om er zelf een te kiezen. Zo kent niemand andermans wachtwoord.
 */
class TrainerController extends Controller
{
    public function store(Request $request): RedirectResponse
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

        $trainer = User::create([
            'school_id' => $request->user()->school_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(32),
        ]);

        $trainer->assignRole(Role::Trainer->value);

        Password::sendResetLink(['email' => $trainer->email]);

        return back()->with(
            'status',
            "{$trainer->name} is toegevoegd als trainer en heeft een e-mail gekregen om een wachtwoord in te stellen."
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
