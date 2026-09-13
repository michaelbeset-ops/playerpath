<?php

namespace App\Actions\Players;

use App\Enums\Role;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Het eigen account van een kind, zonder dat het kind er iets voor hoeft
 * te doen.
 *
 * De kind-link logt het kind in op zijn eigen speleraccount. Heeft de
 * speler er al een (een ouder kind met een eigen inlog), dan is dat het.
 * Anders ontstaat er hier een: naam van het kind, de rol speler, en een
 * adres dat nergens heen gaat. Een kind van acht heeft geen mailbox, dus
 * zo'n account krijgt géén mail (`notification_preferences.mail = false`,
 * zie `User::wantsEmail`); in de app ziet het zijn meldingen gewoon.
 *
 * Het wachtwoord is willekeurig en kent niemand: inloggen gaat alleen via
 * de link van de ouder. Wil de school het kind later een echte inlog
 * geven, dan kan het adres gewoon worden aangepast.
 */
class EnsurePlayerAccount
{
    public const DOMEIN = 'kind.playerpath.nl';

    public function handle(Player $player): User
    {
        if ($player->user !== null) {
            return $player->user;
        }

        return DB::transaction(function () use ($player) {
            $user = User::create([
                'school_id' => $player->school_id,
                'name' => $player->full_name,
                'email' => 'speler-'.$player->id.'-'.Str::lower(Str::random(8)).'@'.self::DOMEIN,
                'password' => Str::random(40),
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'notification_preferences' => ['mail' => false],
            ])->save();

            $user->assignRole(Role::Speler->value);

            $player->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }
}
