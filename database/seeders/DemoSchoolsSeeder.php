<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Training;
use App\Models\User;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Twee scholen met eigen data, zodat je met eigen ogen kunt controleren dat de
 * ????n niets van de ander ziet. Dit is de proef op de som van fase 1.
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

    /**
     * Demo-administratie: een tarief, abonnementen en wat betalingen.
     *
     * Alleen in de seeder. De app zelf maakt geen betalingen aan zolang er
     * geen betaalprovider is aangesloten.
     *
     * @param  Collection<int, Player>  $spelers
     */
    protected function maakAdministratie($spelers): void
    {
        $product = Product::create([
            'name' => 'Keeperstraining',
            'description' => 'Wekelijkse training, inclusief materiaal',
            'amount_cents' => 2750,
            'interval' => BillingInterval::Monthly,
        ]);

        foreach ($spelers as $index => $speler) {
            $abonnement = Subscription::create([
                'player_id' => $speler->id,
                'product_id' => $product->id,
                'amount_cents' => $product->amount_cents,
                'interval' => $product->interval,
                'status' => SubscriptionStatus::Active,
                'payment_method' => PaymentMethod::DirectDebit,
                'starts_on' => now()->subMonths(4)->startOfMonth()->toDateString(),
            ]);

            // Drie maanden historie: betaald, betaald, en de laatste wisselend.
            foreach ([3, 2, 1, 0] as $positie => $maandenGeleden) {
                $moment = now()->subMonths($maandenGeleden)->startOfMonth();

                // De eerste speler heeft een mislukte incasso, zodat je ziet
                // hoe dat eruitziet.
                $status = match (true) {
                    $maandenGeleden === 0 && $index === 0 => PaymentStatus::Failed,
                    $maandenGeleden === 0 => PaymentStatus::Open,
                    default => PaymentStatus::Paid,
                };

                Payment::create([
                    'player_id' => $speler->id,
                    'subscription_id' => $abonnement->id,
                    'amount_cents' => $abonnement->amount_cents,
                    'status' => $status,
                    'method' => PaymentMethod::DirectDebit,
                    'description' => $product->name.' '.$moment->translatedFormat('F Y'),
                    'due_on' => $moment->copy()->addDays(7)->toDateString(),
                    'paid_at' => $status === PaymentStatus::Paid ? $moment->copy()->addDays(3) : null,
                ]);
            }
        }
    }

    /** E??n training geweest, twee komende. */
    protected function maakTrainingen(Group $groep, User $trainer): void
    {
        foreach ([-7, 7, 14] as $dagen) {
            $start = now()->addDays($dagen)->setTime(18, 0);

            $training = Training::create([
                'group_id' => $groep->id,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(90),
                'location' => 'Sportpark De Vliert, veld 3',
            ]);

            $training->trainers()->attach($trainer->id);
        }
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
        // school_id automatisch invult ??? precies zoals de app het straks doet.
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

            // Een echte naam, want die staat straks in een begroeting: "Hallo
            // Ouder van Sem" is geen begroeting maar een rolomschrijving.
            $eersteSpeler = $gemaakteSpelers->first();

            $ouder = User::create([
                'school_id' => $school->id,
                'name' => 'Marieke '.$eersteSpeler->last_name,
                'email' => Str::replace('@', '+ouder@', $eigenaarEmail),
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
            ]);
            $ouder->assignRole(Role::Ouder->value);
            $ouder->children()->attach($eersteSpeler->id, ['relationship' => 'moeder']);

            // En de speler zelf, met een eigen inlog: zo zie je de kaart ook
            // door de ogen van het kind, dat alleen zijn eigen kaart ziet.
            $spelerAccount = User::create([
                'school_id' => $school->id,
                'name' => $eersteSpeler->full_name,
                'email' => Str::replace('@', '+speler@', $eigenaarEmail),
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
            ]);
            $spelerAccount->assignRole(Role::Speler->value);
            $eersteSpeler->update(['user_id' => $spelerAccount->id]);

            // Twee rapporten per speler, zodat de kaarten meteen gevuld zijn
            // en je de doorrekening kunt zien.
            $gemaakteSpelers->each(fn (Player $speler) => $this->maakRapporten($speler, $trainer));

            // Een doel voor de eerste speler, zodat "op koers" meteen iets laat zien.
            $eerste = $gemaakteSpelers->first()->refresh();
            $categorie = $eerste->position->categories()[0];
            $eerste->goals()->create([
                'set_by_id' => $trainer->id,
                'category' => $categorie->value,
                'start_rating' => ($eerste->category_ratings ?? [])[$categorie->value] ?? 0,
                'target_rating' => 80,
                'starts_on' => now()->subWeeks(2)->toDateString(),
                'due_on' => now()->addMonths(2)->toDateString(),
                'note' => 'Focus op de eerste reactie bij schoten van dichtbij.',
            ]);

            // Een training vorige week en twee komende, zodat er meteen iets
            // te zien en af te vinken valt.
            $this->maakTrainingen($gemaakteGroepen->first(), $trainer);

            // Tarieven, abonnementen en een paar betalingen, zodat het
            // financi??le scherm meteen laat zien hoe het eruitziet.
            // Een oud-lid waarvan de bewaartermijn allang verstreken is, zodat
            // het privacyscherm niet leeg staat.
            $gestopt = $gemaakteSpelers->last();
            $this->maakAdministratie($gemaakteSpelers);

            $gestopt->update(['is_active' => false]);
            $gestopt->forceFill(['deactivated_at' => now()->subMonths(30)])->save();
        });
    }
}
