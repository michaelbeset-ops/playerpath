<?php

namespace Database\Seeders;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Twee scholen met eigen data, zodat je met eigen ogen kunt controleren dat de
 * één niets van de ander ziet. Dit is de proef op de som van fase 1.
 */
class DemoSchoolsSeeder extends Seeder
{
    public function run(): void
    {
        $this->maakSchool(
            naam: 'Keepersschool Rob',
            eigenaarEmail: 'rob@playerpath.test',
            spelers: [
                ['Sem', 'de Vries', '2013-04-12', PlayerPosition::Keeper],
                ['Noud', 'Jansen', '2012-09-30', PlayerPosition::Keeper],
                ['Liam', 'Bakker', '2014-01-08', PlayerPosition::Field],
            ],
            groepen: [
                ['Keepers ochtend', 'Onder 11'],
                ['Keepers middag', 'Onder 13'],
            ],
        );

        $this->maakSchool(
            naam: 'Voetbalschool Yoel',
            eigenaarEmail: 'yoel@playerpath.test',
            spelers: [
                ['Daan', 'Visser', '2011-06-21', PlayerPosition::Field],
                ['Mila', 'Smit', '2013-11-03', PlayerPosition::Keeper],
            ],
            groepen: [
                ['Selectie', 'Onder 15'],
            ],
        );
    }

    /** Twee rapporten met lichte groei, zodat de kaart iets laat zien. */
    protected function maakRapporten(Player $speler, User $trainer): void
    {
        foreach ([['-6 weeks', -1], ['-1 week', 0]] as [$wanneer, $verschuiving]) {
            $rapport = Report::create([
                'player_id' => $speler->id,
                'trainer_id' => $trainer->id,
                'reported_on' => now()->modify($wanneer)->toDateString(),
            ]);

            foreach ($speler->position->categories() as $index => $categorie) {
                $rapport->scores()->create([
                    'category' => $categorie->value,
                    'score' => max(1, min(10, 6 + (($index + $speler->id) % 3) + $verschuiving)),
                ]);
            }
        }

        app(CalculatePlayerCard::class)->refresh($speler->refresh());
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: PlayerPosition}>  $spelers
     * @param  array<int, array{0: string, 1: string}>  $groepen
     */
    protected function maakSchool(string $naam, string $eigenaarEmail, array $spelers, array $groepen): void
    {
        $school = School::create([
            'name' => $naam,
            'slug' => Str::slug($naam),
        ]);

        $eigenaar = User::create([
            'school_id' => $school->id,
            'name' => 'Eigenaar '.$naam,
            'email' => $eigenaarEmail,
            'password' => 'wachtwoord',
            'email_verified_at' => now(),
        ]);
        $eigenaar->assignRole(Role::Eigenaar->value);

        $trainer = User::create([
            'school_id' => $school->id,
            'name' => 'Trainer '.$naam,
            'email' => Str::replace('@', '+trainer@', $eigenaarEmail),
            'password' => 'wachtwoord',
            'email_verified_at' => now(),
        ]);
        $trainer->assignRole(Role::Trainer->value);

        // Alles hieronder draait binnen deze school, zodat de scope het
        // school_id automatisch invult — precies zoals de app het straks doet.
        app(Tenancy::class)->forSchool($school, function () use ($spelers, $groepen, $school, $eigenaarEmail, $trainer) {
            $gemaakteGroepen = collect($groepen)->map(fn (array $groep) => Group::create([
                'name' => $groep[0],
                'age_category' => $groep[1],
            ]));

            $gemaakteSpelers = collect($spelers)->map(fn (array $speler) => Player::create([
                'first_name' => $speler[0],
                'last_name' => $speler[1],
                'date_of_birth' => $speler[2],
                'position' => $speler[3],
            ]));

            // Iedereen in de eerste groep; indelen blijft handwerk, dit is alleen demo-data.
            $gemaakteSpelers->each(fn (Player $speler) => $speler->groups()->sync([$gemaakteGroepen->first()->id]));

            $ouder = User::create([
                'school_id' => $school->id,
                'name' => 'Ouder van '.$gemaakteSpelers->first()->first_name,
                'email' => Str::replace('@', '+ouder@', $eigenaarEmail),
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
            ]);
            $ouder->assignRole(Role::Ouder->value);
            $ouder->children()->attach($gemaakteSpelers->first()->id, ['relationship' => 'moeder']);

            // Twee rapporten per speler, zodat de kaarten meteen gevuld zijn
            // en je de doorrekening kunt zien.
            $gemaakteSpelers->each(fn (Player $speler) => $this->maakRapporten($speler, $trainer));
        });
    }
}
