<?php

namespace App\Actions\Platform;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Een school en alles wat eraan hangt definitief verwijderen.
 *
 * Dit is het einde van een klantrelatie: de school zegt op, de gegevens gaan
 * weg en niemand van die school kan nog inloggen. Onomkeerbaar, dus de
 * bevestiging in het scherm vraagt om de naam van de school — niet om een
 * kliksnelle "OK".
 *
 * De databasesleutels doen het meeste werk: elke tabel met school_id staat op
 * cascadeOnDelete, dus spelers, rapporten, trainingen, betalingen en accounts
 * verdwijnen automatisch. Drie dingen doen dat níét, omdat ze geen school_id
 * hebben, en die ruimen we hier zelf op:
 *
 * 1. **Meldingen** (`notifications`) hangen polymorf aan de gebruiker.
 * 2. **Sessies** hangen aan user_id zonder sleutel. Weghalen zorgt dat een
 *    ingelogde trainer er meteen uit ligt in plaats van bij zijn volgende klik.
 * 3. **Wachtwoord-reset-tokens** staan op e-mailadres. Een oude link mag geen
 *    account meer kunnen herstellen.
 *
 * Het logboek blijft wél staan: `platform_logs.school_id` en
 * `impersonations.school_id` zijn nullOnDelete, en de naam van de school staat
 * er als tekst bij. Juist bij een verwijdering wil je later kunnen zien dat
 * het gebeurd is.
 */
class DeleteSchool
{
    /**
     * Wat er verdwijnt, zodat het scherm het kan tonen vóór de bevestiging.
     *
     * Hier wordt de scope bewust opzijgezet en de school met de hand ingevuld.
     * Dit draait namelijk in de beheeromgeving, en daar staat de scope juist
     * open — een gewone `Player::count()` zou dan de spelers van álle scholen
     * tellen en een veel te groot getal in de bevestiging zetten.
     *
     * @return array<string, int>
     */
    public function summarise(School $school): array
    {
        $tel = fn (string $model) => $model::withoutSchoolScope()->where('school_id', $school->id)->count();

        return [
            'users' => $school->users()->where('school_id', $school->id)->count(),
            'players' => $tel(Player::class),
            'reports' => $tel(Report::class),
            'trainings' => $tel(Training::class),
            'payments' => $tel(Payment::class),
            'enrollments' => $tel(Enrollment::class),
        ];
    }

    /**
     * @return array<string, int> wat er is verwijderd
     */
    public function handle(School $school): array
    {
        $aantallen = $this->summarise($school);
        $gebruikers = $school->users()->where('school_id', $school->id)->get(['id', 'email']);
        $gebruikerIds = $gebruikers->pluck('id')->all();
        $emails = $gebruikers->pluck('email')->all();

        DB::transaction(function () use ($school, $gebruikerIds, $emails) {
            if ($gebruikerIds !== []) {
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->whereIn('notifiable_id', $gebruikerIds)
                    ->delete();

                if (DB::getSchemaBuilder()->hasTable('sessions')) {
                    DB::table('sessions')->whereIn('user_id', $gebruikerIds)->delete();
                }
            }

            if ($emails !== []) {
                DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            }

            // De rest gaat mee via cascadeOnDelete.
            $school->delete();
        });

        return $aantallen;
    }
}
