<?php

namespace App\Http\Controllers\Players;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Ouders koppelen aan hun kind.
 *
 * Zelfregistratie staat dicht, dus de eigenaar maakt het ouder-account aan.
 * We zetten daarbij geen wachtwoord: de ouder krijgt een e-mail om er zelf
 * een te kiezen, via dezelfde route als "wachtwoord vergeten". Zo staat er
 * nergens een wachtwoord dat iemand anders kent.
 */
class GuardianController extends Controller
{
    /** Koppel een bestaande ouder van deze school aan de speler. */
    public function store(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('update', $player);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                // Alleen gebruikers van de eigen school: User heeft geen global
                // scope, dus die grens leggen we hier expliciet.
                Rule::exists('users', 'id')->where('school_id', $player->school_id),
            ],
            'relationship' => ['nullable', 'string', 'max:255'],
        ], [], [
            'user_id' => 'De ouder',
            'relationship' => 'De relatie',
        ]);

        $player->guardians()->syncWithoutDetaching([
            $validated['user_id'] => ['relationship' => $validated['relationship'] ?? null],
        ]);

        return back()->with('status', 'De ouder is gekoppeld aan deze speler.');
    }

    /** Maak een nieuw ouder-account en koppel het meteen. */
    public function invite(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('update', $player);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'relationship' => ['nullable', 'string', 'max:255'],
        ], [
            'email.unique' => 'Er bestaat al een account met dit e-mailadres. Koppel die ouder via de lijst hierboven.',
        ], [
            'name' => 'De naam',
            'email' => 'Het e-mailadres',
            'relationship' => 'De relatie',
        ]);

        $ouder = User::create([
            'school_id' => $player->school_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(32),
        ]);

        $ouder->assignRole(Role::Ouder->value);

        $player->guardians()->syncWithoutDetaching([
            $ouder->id => ['relationship' => $validated['relationship'] ?? null],
        ]);

        Password::sendResetLink(['email' => $ouder->email]);

        return back()->with(
            'status',
            "{$ouder->name} is gekoppeld en heeft een e-mail gekregen om een wachtwoord in te stellen."
        );
    }

    public function destroy(Player $player, User $guardian): RedirectResponse
    {
        $this->authorize('update', $player);

        abort_unless($player->school_id === $guardian->school_id, 404);

        $player->guardians()->detach($guardian->id);

        return back()->with('status', 'De koppeling met deze ouder is verwijderd.');
    }
}
