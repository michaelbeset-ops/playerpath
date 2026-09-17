<?php

namespace App\Actions\Onboarding;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\School;
use App\Models\User;
use App\Notifications\Uitnodiging;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Iemand uitnodigen: de enige manier waarop een school er een mens bij krijgt.
 *
 * Elke weg komt hier uit - het formulier op Personeel en bij een kind, de
 * oude routes voor een ouder of trainer, en in platformbeheer een eigenaar bij
 * een nieuwe school of een account voor een school. Eerst maakten die laatste
 * meteen een account aan en stuurden ze de mail "Kies een nieuw wachtwoord".
 * Dat leest als een fout voor iemand die nog nooit een wachtwoord had, en die
 * reset-link verloopt na een uur.
 *
 * Nu is het overal hetzelfde: een uitnodiging met een welkomstmail uit naam
 * van de school (`Uitnodiging`), geldig zo lang als de school instelt
 * (standaard veertien dagen), en het account ontstaat pas bij activatie.
 *
 * De school wordt expliciet ingevuld en niet uit de actieve school gehaald: in
 * platformbeheer is er geen actieve school, en dan zou opslaan stuklopen.
 */
class SendInvitation
{
    /**
     * @param  list<int>  $playerIds  bij een ouder: de kinderen die bij activatie gekoppeld worden; bij een speler: zijn eigen profiel
     * @param  bool  $send  false als de aanroeper de mails pas na zijn eigen transactie verstuurt
     * @param  User|null  $account  een bestaand account zonder inlog (een trainer uit een import) dat bij activatie het adres en wachtwoord krijgt
     */
    public function handle(
        School $school,
        string $name,
        string $email,
        string $role,
        ?User $inviter = null,
        array $playerIds = [],
        ?string $relationship = null,
        bool $send = true,
        ?User $account = null,
    ): Invitation {
        $email = strtolower(trim($email));
        $dagen = (int) ($school->invitation_valid_days ?: 14);

        $uitnodiging = DB::transaction(function () use ($school, $name, $email, $role, $inviter, $playerIds, $relationship, $dagen, $account) {
            // Een openstaande uitnodiging voor hetzelfde adres wordt vervangen,
            // niet verdubbeld: twee mails met twee links is verwarrend.
            Invitation::withoutSchoolScope()
                ->where('school_id', $school->id)
                ->where(fn ($q) => $q->where('email', $email)
                    ->when($account, fn ($q) => $q->orWhere('user_id', $account->id)))
                ->pending()
                ->delete();

            $rij = new Invitation;
            $rij->forceFill([
                'school_id' => $school->id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'token' => Invitation::nieuwToken(),
                'player_ids' => in_array($role, [Role::Ouder->value, Role::Speler->value], true) ? array_values($playerIds) : null,
                'relationship' => $role === Role::Ouder->value ? $relationship : null,
                'invited_by' => $inviter?->id,
                'expires_at' => now()->addDays($dagen),
                'last_sent_at' => now(),
                'sent_count' => 1,
                // Vóór activatie: het account dat deze uitnodiging activeert.
                // Daarna: het account dat eruit ontstond.
                'user_id' => $account?->id,
            ])->save();

            return $rij;
        });

        if ($send) {
            $this->send($uitnodiging);
        }

        return $uitnodiging;
    }

    /** De welkomstmail versturen. Na de transactie: een mislukte opslag mag geen mail opleveren. */
    public function send(Invitation $invitation): void
    {
        Notification::route('mail', $invitation->email)->notify(new Uitnodiging($invitation));
    }
}
