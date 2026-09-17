<?php

namespace App\Http\Controllers\Players;

use App\Actions\Onboarding\SendInvitation;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ouders koppelen aan hun kind.
 *
 * Zelfregistratie staat dicht, dus de school nodigt de ouder uit. Er wordt
 * geen account aangemaakt en geen wachtwoord gezet: de ouder krijgt een
 * welkomstmail uit naam van de school, activeert zijn account en kiest daar
 * zelf een wachtwoord. Het kind wordt bij het activeren meteen gekoppeld.
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
                // En alleen een ouder: een trainer of speler aan een kind
                // koppelen geeft hem de kaart en de betalingen van dat kind.
                function (string $attribute, mixed $value, \Closure $fail) use ($player) {
                    $isOuder = User::query()
                        ->whereKey($value)
                        ->where('school_id', $player->school_id)
                        ->role(Role::Ouder->value)
                        ->exists();

                    if (! $isOuder) {
                        $fail('Je kunt alleen een ouder van deze school koppelen.');
                    }
                },
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

    /**
     * Een nieuwe ouder uitnodigen voor dit kind.
     *
     * Dit maakte vroeger meteen een account en stuurde de mail "Kies een nieuw
     * wachtwoord". Nu is het een gewone uitnodiging (SendInvitation), zoals
     * het formulier op de pagina van het kind al deed.
     */
    public function invite(Request $request, Player $player, SendInvitation $uitnodigen): RedirectResponse
    {
        $this->authorize('update', $player);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                // Een e-mailadres is uniek over alle scholen heen. Het advies om
                // via de lijst te koppelen geldt alleen voor een account van
                // déze school; voor de rest één neutrale melding, zodat dit
                // formulier niet verraadt wie er bij een andere school zit.
                function (string $attribute, mixed $value, \Closure $fail) use ($player) {
                    $bestaand = User::query()->where('email', $value)->first(['id', 'school_id']);

                    if ($bestaand === null) {
                        return;
                    }

                    $fail($bestaand->school_id === $player->school_id && $bestaand->hasRole(Role::Ouder->value)
                        ? 'Er bestaat al een account met dit e-mailadres. Koppel die ouder via de lijst hierboven.'
                        : 'Dit e-mailadres is al in gebruik.');
                },
            ],
            'relationship' => ['nullable', 'string', 'max:255'],
        ], [], [
            'name' => 'De naam',
            'email' => 'Het e-mailadres',
            'relationship' => 'De relatie',
        ]);

        $uitnodigen->handle(
            $player->school,
            $validated['name'],
            $validated['email'],
            Role::Ouder->value,
            $request->user(),
            [$player->id],
            $validated['relationship'] ?? null,
        );

        return back()->with(
            'status',
            "{$validated['name']} krijgt een welkomstmail om het account te activeren. {$player->first_name} wordt daarbij meteen gekoppeld."
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
