<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\BillingInterval;
use App\Enums\BillingType;
use App\Enums\OfferingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlayerPosition;
use App\Enums\ProductAudience;
use App\Enums\ProductType;
use App\Enums\Registration;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Report;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Training;
use App\Models\User;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Onboarding\OnboardingState;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Rating\RatingEngine;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Voetbalschool PlayerPath: een volle school voor screenshots en demo's.
 *
 * Geen willekeur: de cijfers op het dashboard zijn gekozen. 34 actieve
 * spelers, gemiddelde rating 79, 29 rapporten deze week, € 2.375,00 omzet
 * deze maand, € 280,00 openstaand en 18 lopende abonnementen. Trainingen
 * over verschillende dagen, groepen, locaties en trainers, met vol en
 * "nog 2 plekken". Een ouder met twee kinderen: één rekening betaald, één
 * open. Alles in het Nederlands, verzonnen namen, geen foto's.
 *
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * Opnieuw draaien vervangt de school (zelfde slug). Het wachtwoord van de
 * accounts komt uit SHOWCASE_PASSWORD, anders wordt er een gemaakt en
 * getoond.
 */
class ShowcaseSeeder extends Seeder
{
    public const SLUG = 'voetbalschool-playerpath';

    protected string $wachtwoord;

    /** @var array<string, User> */
    protected array $trainers = [];

    /** @var array<string, Location> */
    protected array $locaties = [];

    /** @var array<string, Group> */
    protected array $groepen = [];

    /** @var Collection<int, Player> */
    protected Collection $spelers;

    /** @var array<int, int> streefcijfer per speler-id */
    protected array $streef = [];

    protected User $eigenaar;

    public function run(): void
    {
        $this->wachtwoord = (string) (env('SHOWCASE_PASSWORD') ?: 'Pp-'.Str::random(10));

        School::withoutGlobalScopes()->where('slug', self::SLUG)->get()->each(function (School $oud) {
            User::where('school_id', $oud->id)->delete();
            $oud->delete();
        });

        $school = School::create([
            'name' => 'Voetbalschool PlayerPath',
            'slug' => self::SLUG,
            'contact_name' => 'Michael Beset',
            'contact_email' => 'info@playerpath.nl',
            'contact_phone' => '06 43 43 00 03',
        ]);

        $eigenaar = $this->eigenaar = $this->account($school, 'Michael Beset', 'eigenaar@playerpath.nl', Role::Eigenaar);

        app(Tenancy::class)->forSchool($school, function () use ($school, $eigenaar) {
            $this->trainers = [
                'jesse' => $this->account($school, 'Jesse van Dijk', 'jesse@playerpath.nl', Role::Trainer),
                'sanne' => $this->account($school, 'Sanne Willems', 'sanne@playerpath.nl', Role::Trainer),
                'daan' => $this->account($school, 'Daan Mulder', 'daan@playerpath.nl', Role::Trainer),
            ];

            $this->locaties = [
                'vliert' => Location::create(['name' => 'Sportpark De Vliert', 'address' => 'Vlietweg 12, Rotterdam']),
                'hal' => Location::create(['name' => 'Sporthal Oost', 'address' => 'Oostplein 4, Rotterdam']),
                'kunstgras' => Location::create(['name' => 'Kunstgrasveld Zuidwijk', 'address' => 'Slinge 220, Rotterdam']),
            ];

            $this->groepen = [
                'o10' => Group::create(['name' => 'Keepers O10', 'age_category' => 'Onder 10']),
                'o12' => Group::create(['name' => 'Keepers O12', 'age_category' => 'Onder 12']),
                'o14' => Group::create(['name' => 'Keepers O14', 'age_category' => 'Onder 14']),
                'veld' => Group::create(['name' => 'Techniek veldspelers', 'age_category' => 'Onder 13']),
            ];

            $this->spelers = $this->maakSpelers();
            $this->maakTrainingen();
            $this->maakRapporten();
            $ouders = $this->maakOuders($school);
            $aanbod = $this->maakAanbod();
            $this->maakAdministratie($aanbod, $ouders);
            $this->maakDoelen();

            // Geen rondleiding, geen startlijst, geen wizard: dit is een
            // draaiende school, niet een nieuwe.
            OnboardingState::save($school, ['tour_seen_at' => now()->toIso8601String(), 'checklist_completed_at' => now()->toIso8601String(), 'wizard_step' => 9]);
            EnrollmentSettings::save($school, ['offering_types' => ['doorlopend', 'blok', 'kamp', 'proefles']]);
            EnrollmentSettings::complete($school);
            $eigenaar->forceFill(['intro_seen_at' => now()])->save();
        });

        $this->command?->info('Voetbalschool PlayerPath staat klaar.');
        $this->command?->table(['Rol', 'E-mail', 'Wachtwoord'], [
            ['Eigenaar', 'eigenaar@playerpath.nl', $this->wachtwoord],
            ['Trainer', 'jesse@playerpath.nl', $this->wachtwoord],
            ['Ouder (Sem en Fenna)', 'ouder@playerpath.nl', $this->wachtwoord],
        ]);
        $this->command?->line('Inschrijfpagina: /inschrijven/'.self::SLUG);
    }

    protected function account(School $school, string $naam, string $email, Role $rol): User
    {
        User::where('email', $email)->delete();

        $user = User::create([
            'school_id' => $school->id,
            'name' => $naam,
            'email' => $email,
            'password' => $this->wachtwoord,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($rol->value);
        $user->forceFill(['intro_seen_at' => now()])->save();

        return $user;
    }

    /**
     * 35 spelers, 34 actief. Per speler een streefcijfer voor de kaart zodat
     * het gemiddelde precies op 79 uitkomt.
     *
     * @return Collection<int, Player>
     */
    protected function maakSpelers(): Collection
    {
        $namen = [
            // [voornaam, achternaam, geboortejaar, groep, positie]
            ['Sem', 'de Vries', 2016, 'o10', 'keeper'], ['Lucas', 'Jansen', 2017, 'o10', 'keeper'], ['Finn', 'Bakker', 2016, 'o10', 'keeper'],
            ['Milan', 'Visser', 2017, 'o10', 'keeper'], ['Noor', 'Smit', 2016, 'o10', 'keeper'], ['Jayden', 'Meijer', 2017, 'o10', 'keeper'],
            ['Liam', 'de Boer', 2016, 'o10', 'keeper'], ['Sara', 'Mulder', 2016, 'o10', 'keeper'],
            ['Noud', 'Bos', 2014, 'o12', 'keeper'], ['Levi', 'Vos', 2015, 'o12', 'keeper'], ['Tess', 'Peters', 2014, 'o12', 'keeper'],
            ['Mees', 'Hendriks', 2015, 'o12', 'keeper'], ['Julia', 'van Leeuwen', 2014, 'o12', 'keeper'], ['Bram', 'Dekker', 2015, 'o12', 'keeper'],
            ['Fenna', 'de Vries', 2014, 'o12', 'keeper'], ['Thijs', 'Brouwer', 2015, 'o12', 'keeper'], ['Sofie', 'van Dam', 2014, 'o12', 'keeper'],
            ['Daan', 'Kok', 2012, 'o14', 'keeper'], ['Luuk', 'Jacobs', 2013, 'o14', 'keeper'], ['Evi', 'de Groot', 2012, 'o14', 'keeper'],
            ['Ruben', 'Vermeulen', 2013, 'o14', 'keeper'], ['Zoë', 'van den Berg', 2012, 'o14', 'keeper'], ['Sven', 'Kuipers', 2013, 'o14', 'keeper'],
            ['Isa', 'Prins', 2012, 'o14', 'keeper'], ['Jens', 'Willemsen', 2013, 'o14', 'keeper'],
            ['Adam', 'Hoekstra', 2013, 'veld', 'field'], ['Lotte', 'Schouten', 2014, 'veld', 'field'], ['Mohammed', 'El Amrani', 2013, 'veld', 'field'],
            ['Nina', 'Koster', 2014, 'veld', 'field'], ['Timo', 'van Vliet', 2013, 'veld', 'field'], ['Yara', 'Postma', 2014, 'veld', 'field'],
            ['Boaz', 'Timmermans', 2013, 'veld', 'field'], ['Elin', 'Scholten', 2014, 'veld', 'field'], ['Kai', 'Blom', 2013, 'veld', 'field'],
            // De 35e is gestopt: telt niet mee.
            ['Olivier', 'Groen', 2012, 'o14', 'keeper'],
        ];

        // 34 streefcijfers die samen precies 34 × 79 = 2686 maken.
        $streef = [72, 73, 74, 74, 75, 75, 76, 76, 77, 77, 77, 78, 78, 78, 78, 79, 79, 79, 79, 80, 80, 80, 80, 81, 81, 81, 82, 82, 83, 83, 84, 85, 86, 87];
        $maanden = [3, 7, 11, 1, 5, 9, 2, 6, 10, 4, 8, 12];

        return collect($namen)->map(function (array $n, int $i) use ($streef, $maanden) {
            $speler = Player::create([
                'first_name' => $n[0],
                'last_name' => $n[1],
                'date_of_birth' => sprintf('%d-%02d-%02d', $n[2], $maanden[$i % 12], 3 + ($i * 7) % 25),
                'position' => $n[4] === 'keeper' ? PlayerPosition::Keeper : PlayerPosition::Field,
                'shirt_number' => $i % 5 === 0 ? 1 + ($i % 23) : null,
                'is_active' => $i < 34,
            ]);
            $speler->groups()->sync([$this->groepen[$n[3]]->id]);
            // Geen "34 erbij deze maand": ze zitten er al een tijd.
            $speler->forceFill(['created_at' => now()->subMonths(2 + ($i % 6))->subDays($i % 20)])->save();
            $this->streef[$speler->id] = $streef[$i] ?? 70;

            return $speler;
        });
    }

    /**
     * Acht weken trainingen per groep, afgevinkt, plus de komende week.
     */
    protected function maakTrainingen(): void
    {
        $rooster = [
            // groep, weekdag (1 = maandag), uur, locatie, trainer
            ['o10', 1, 16, 'vliert', 'sanne'],
            ['o12', 2, 17, 'hal', 'jesse'],
            ['o14', 3, 18, 'kunstgras', 'jesse'],
            ['veld', 4, 17, 'vliert', 'daan'],
            ['o12', 6, 10, 'kunstgras', 'sanne'],
        ];

        $engine = app(RatingEngine::class);
        $weekStart = now()->startOfWeek();

        foreach ($rooster as $i => [$groep, $dag, $uur, $locatie, $trainer]) {
            for ($w = -8; $w <= 1; $w++) {
                $start = $weekStart->copy()->addWeeks($w)->addDays($dag - 1)->setTime($uur, 0);

                $training = Training::create([
                    'group_id' => $this->groepen[$groep]->id,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes(75),
                    'location' => $this->locaties[$locatie]->name,
                    'location_id' => $this->locaties[$locatie]->id,
                    // Komende trainingen: los inschrijven aan, om en om vol
                    // en met twee plekken over.
                    'open_enrollment' => $start->isFuture(),
                    'capacity' => $start->isFuture() ? $this->groepen[$groep]->players()->where('is_active', true)->count() + ($i % 2 === 0 ? 0 : 2) : null,
                    'price_cents' => $start->isFuture() ? 1500 : 0,
                    'payment_methods' => ['online', 'cash'],
                    'audience' => $groep === 'veld' ? ProductAudience::Field : ProductAudience::Keeper,
                ]);
                $training->trainers()->attach($this->trainers[$trainer]->id);

                if (! $start->isPast()) {
                    continue;
                }

                foreach ($this->groepen[$groep]->players as $k => $speler) {
                    // Opkomst rond de 85%: wie er niet was, is om de zoveel keer afwezig.
                    $aanwezig = (($k + $w + $i) % 7) !== 0;

                    $aanwezigheid = Attendance::create([
                        'training_id' => $training->id,
                        'player_id' => $speler->id,
                        'registration' => $aanwezig ? Registration::Attending : Registration::Declined,
                        'status' => $aanwezig ? AttendanceStatus::Present : AttendanceStatus::Absent,
                    ]);

                    if ($aanwezig) {
                        $engine->award($speler, 'attendance', $engine->settingsFor($speler)->xpForAttendance(), 'Aanwezig bij '.$training->label(), $aanwezigheid, $start);
                    }
                }
            }
        }
    }

    /**
     * Drie rapporten per actieve speler. De laatste drie bepalen de kaart:
     * s-0,2 / s / s+0,2 middelt precies op s, en per categorie een vaste
     * afwijking die samen nul is — zo komt de overall exact op het streefcijfer.
     * 29 spelers kregen hun laatste rapport deze week.
     */
    protected function maakRapporten(): void
    {
        $engine = app(RatingEngine::class);
        $afwijking = [-0.3, -0.1, 0.0, 0.1, 0.2, 0.1];
        // De eigenaar traint zelf ook mee: anders zegt het dashboard dat hij
        // deze week nog geen rapport invulde.
        $trainers = [$this->trainers['jesse'], $this->trainers['sanne'], $this->trainers['daan'], $this->eigenaar];
        $weekStart = now()->startOfWeek();
        $dezeWeek = [0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5];

        foreach ($this->spelers->where('is_active', true)->values() as $i => $speler) {
            $s = $this->streef[$speler->id] / 10;
            $trainer = $trainers[$i % 4];

            // De laatste ligt deze week (29 spelers) of vorige week (5 spelers).
            $laatste = $i < 29
                ? $weekStart->copy()->addDays($dezeWeek[$i % count($dezeWeek)])
                : $weekStart->copy()->subDays(2 + ($i % 4));
            $laatste = $laatste->min(now());

            // Drie rapporten: één in augustus (lager, zodat "vorige maand"
            // een stijging laat zien), één eerder deze maand en de laatste.
            // Het maandgemiddelde van de laatste twee is precies s; wie
            // daalt gaat van s+0,2 naar s-0,2, de rest andersom.
            // Een derde kreeg het vorige rapport twee weken terug (niet in
            // "vorige week"), de rest zes dagen terug; het maandgemiddelde
            // blijft precies s.
            $richting = in_array($i, [4, 17, 30], true) ? -1 : 1;
            $tweeWeken = $i % 3 === 0;
            $momenten = [
                [now()->subMonth()->startOfMonth()->addDays(9 + ($i % 6)), $s - 0.4],
                [$laatste->copy()->subDays($tweeWeken ? 14 : 6), $s - 0.2 * $richting],
                [$laatste, $tweeWeken ? $s : $s + 0.2 * $richting],
            ];

            foreach ($momenten as [$datum, $basis]) {
                $rapport = Report::create([
                    'player_id' => $speler->id,
                    'trainer_id' => $trainer->id,
                    'reported_on' => $datum->toDateString(),
                    'note' => $datum->isSameDay($laatste) ? $this->notitie($i) : null,
                ]);

                foreach ($speler->position->categories() as $k => $categorie) {
                    $rapport->scores()->create([
                        'category' => $categorie->value,
                        'score' => round(max(1, min(10, $basis + $afwijking[$k])), 1),
                    ]);
                }

                $engine->award($speler, 'report', $engine->settingsFor($speler)->xpForReport(), 'Rapport van '.$trainer->name, $rapport, $datum);
            }

            // Groei-XP verdeelt de levels: een paar spelers zitten op zilver of goud.
            if ($i % 3 === 0) {
                $engine->award($speler, 'growth', 40 + ($i % 4) * 30, 'Groei op de kaart', null, now()->subWeek());
            }

            app(CalculatePlayerCard::class)->refresh($speler->refresh());
        }
    }

    protected function notitie(int $i): ?string
    {
        $noten = [
            'Sterk in de 1-tegen-1, blijft laag en rustig.',
            'Voetenwerk gaat vooruit; nog even doorzetten op de hoge bal.',
            'Goede training, communiceert steeds duidelijker naar de verdediging.',
            null,
            'Reflexen top vandaag. Uitkomen mag brutaler.',
            null,
        ];

        return $noten[$i % count($noten)];
    }

    /**
     * Ouders: één per gezin, en één ouder met twee kinderen voor het
     * ouderscherm (Sem en Fenna de Vries).
     *
     * @return Collection<int, User>
     */
    protected function maakOuders(School $school): Collection
    {
        $ouder = $this->account($school, 'Marieke de Vries', 'ouder@playerpath.nl', Role::Ouder);
        $ouder->children()->attach($this->spelers->firstWhere('first_name', 'Sem')->id, ['relationship' => 'moeder']);
        $ouder->children()->attach($this->spelers->firstWhere('first_name', 'Fenna')->id, ['relationship' => 'moeder']);

        $ouders = collect([$ouder]);
        $voornamen = ['Kim', 'Bas', 'Ilse', 'Jeroen', 'Femke', 'Tom', 'Anouk', 'Mark', 'Lisa', 'Rick', 'Eva', 'Joris', 'Maud', 'Niels', 'Roos', 'Stijn', 'Marit', 'Koen', 'Amber', 'Wouter', 'Iris', 'Gijs', 'Esmee', 'Pim', 'Floor', 'Lars', 'Britt', 'Tijn', 'Vera', 'Hugo', 'Loes', 'Max'];

        foreach ($this->spelers->where('is_active', true)->values() as $i => $speler) {
            if (in_array($speler->first_name, ['Sem', 'Fenna'], true)) {
                continue;
            }

            $naam = $voornamen[$i % count($voornamen)].' '.$speler->last_name;
            $email = Str::slug($voornamen[$i % count($voornamen)].'.'.$speler->last_name).'@voorbeeld.nl';
            $ouderVan = $this->account($school, $naam, $email, Role::Ouder);
            $ouderVan->children()->attach($speler->id, ['relationship' => $i % 2 === 0 ? 'moeder' : 'vader']);
            $ouders->push($ouderVan);
        }

        return $ouders;
    }

    /**
     * Het aanbod, met meerdere betaalvormen zodat de inschrijfpagina een
     * tariefkeuze toont.
     *
     * @return array<string, Product>
     */
    protected function maakAanbod(): array
    {
        $keepers = Product::create([
            'name' => 'Keeperstraining wekelijks',
            'amount_cents' => 5950,
            'billing_type' => BillingType::Maandelijks,
            'description' => 'Elke week een keeperstraining van 75 minuten in een kleine groep, ingedeeld op leeftijd. Inclusief keepershandschoenen bij de start.',
            'type' => ProductType::Doorlopend,
            'audience' => ProductAudience::Keeper,
            'min_age' => 7,
            'max_age' => 15,
            'vat_rate' => 9,
            'status' => OfferingStatus::Open,
            'location_id' => $this->locaties['vliert']->id,
            'location' => $this->locaties['vliert']->name,
            'capacity' => 40,
            'schedule' => ['days' => [1, 2, 3], 'time' => '17:00'],
            'is_active' => true,
        ]);
        $keepers->syncPaymentOptions([
            ['type' => 'abonnement', 'label' => 'Per maand', 'amount_cents' => 5950, 'interval' => 'monthly'],
            ['type' => 'eenmalig', 'label' => 'Heel seizoen ineens', 'amount_cents' => 53500],
        ]);

        $veld = Product::create([
            'name' => 'Techniektraining veldspelers',
            'amount_cents' => 6500,
            'billing_type' => BillingType::Maandelijks,
            'description' => 'Wekelijkse techniektraining: passing, aannemen, afwerken. Voor veldspelers van 9 tot 14 jaar.',
            'type' => ProductType::Doorlopend,
            'audience' => ProductAudience::Field,
            'min_age' => 9,
            'max_age' => 14,
            'vat_rate' => 9,
            'status' => OfferingStatus::Open,
            'location_id' => $this->locaties['vliert']->id,
            'location' => $this->locaties['vliert']->name,
            'capacity' => 16,
            'schedule' => ['days' => [4], 'time' => '17:00'],
            'is_active' => true,
        ]);
        $veld->syncPaymentOptions([
            ['type' => 'abonnement', 'label' => 'Per maand', 'amount_cents' => 6500, 'interval' => 'monthly'],
        ]);

        $kamp = Product::create([
            'name' => 'Herfstkamp keepers',
            'amount_cents' => 14900,
            'billing_type' => BillingType::Eenmalig,
            'description' => 'Drie dagen keepen in de herfstvakantie: techniek, wedstrijdvormen en een echte keeperstest. Inclusief lunch en kampshirt.',
            'type' => ProductType::Kamp,
            'audience' => ProductAudience::Keeper,
            'min_age' => 8,
            'max_age' => 14,
            'vat_rate' => 9,
            'status' => OfferingStatus::Open,
            'starts_on' => now()->addWeeks(6)->next(Carbon::MONDAY)->toDateString(),
            'ends_on' => now()->addWeeks(6)->next(Carbon::MONDAY)->addDays(2)->toDateString(),
            'sessions_count' => 3,
            'capacity' => 24,
            'location_id' => $this->locaties['kunstgras']->id,
            'location' => $this->locaties['kunstgras']->name,
            'is_active' => true,
        ]);
        $kamp->syncPaymentOptions([
            ['type' => 'eenmalig', 'label' => 'Ineens', 'amount_cents' => 14900],
            ['type' => 'termijnen', 'label' => 'In 2 termijnen', 'amount_cents' => 7450, 'installments' => 2, 'interval' => 'month'],
        ]);

        $blok = Product::create([
            'name' => 'Keepersblok 6 weken',
            'amount_cents' => 12900,
            'billing_type' => BillingType::Eenmalig,
            'description' => 'Zes weken intensief keepen op zaterdagochtend, voor wie wil proeven of wil bijtrainen naast de club.',
            'type' => ProductType::Blok,
            'audience' => ProductAudience::Keeper,
            'min_age' => 8,
            'max_age' => 14,
            'vat_rate' => 9,
            'status' => OfferingStatus::Open,
            'starts_on' => now()->next(Carbon::SATURDAY)->toDateString(),
            'ends_on' => now()->next(Carbon::SATURDAY)->addWeeks(5)->toDateString(),
            'sessions_count' => 6,
            'capacity' => 12,
            'location_id' => $this->locaties['kunstgras']->id,
            'location' => $this->locaties['kunstgras']->name,
            'is_active' => true,
        ]);
        $blok->syncPaymentOptions([
            ['type' => 'eenmalig', 'label' => 'Ineens', 'amount_cents' => 12900],
            ['type' => 'termijnen', 'label' => 'In 3 termijnen', 'amount_cents' => 4300, 'installments' => 3, 'interval' => 'month'],
        ]);

        $proefles = Product::create([
            'name' => 'Proefles',
            'amount_cents' => 1250,
            'billing_type' => BillingType::Eenmalig,
            'description' => 'Een keer meetrainen om te kijken of het bevalt. Daarna beslis je.',
            'type' => ProductType::Proefles,
            'audience' => ProductAudience::All,
            'min_age' => 7,
            'max_age' => 15,
            'vat_rate' => 9,
            'status' => OfferingStatus::Open,
            'location_id' => $this->locaties['vliert']->id,
            'location' => $this->locaties['vliert']->name,
            'is_active' => true,
        ]);
        $proefles->syncPaymentOptions([
            ['type' => 'eenmalig', 'label' => 'Eenmalig', 'amount_cents' => 1250],
        ]);

        return compact('keepers', 'veld', 'kamp', 'blok', 'proefles');
    }

    /**
     * De administratie, uitgerekend op de cijfers van het dashboard:
     *
     * - 18 lopende abonnementen: 12 keepers à € 59,50 en 6 veld à € 65,00.
     *   Deze maand betaald: 10 × 59,50 + 6 × 65,00 = € 985,00. Twee keepers
     *   hebben hun termijn nog open (vervallen: achterstallig).
     * - Herfstkamp: 5 × € 149,00 betaald = € 745,00.
     * - Keepersblok: 5 × € 129,00 betaald = € 645,00.
     *   Samen € 2.375,00 omzet deze maand.
     * - Openstaand: 2 × 59,50 (open) + 65,00 (mislukte incasso) + 96,00
     *   (open kampaanbetaling) = € 280,00.
     * - Vorige maanden: alles betaald, zodat het overzicht historie heeft.
     *
     * @param  array<string, Product>  $aanbod
     * @param  Collection<int, User>  $ouders
     */
    protected function maakAdministratie(array $aanbod, Collection $ouders): void
    {
        $actief = $this->spelers->where('is_active', true)->values();
        $keepers = $actief->where('position', PlayerPosition::Keeper)->values();
        $veld = $actief->where('position', PlayerPosition::Field)->values();

        // Sem (kind van de showcase-ouder) betaald; Fenna (ook haar kind) open.
        $keeperAbonnees = collect([$keepers->firstWhere('first_name', 'Sem'), $keepers->firstWhere('first_name', 'Fenna')])
            ->merge($keepers->reject(fn (Player $p) => in_array($p->first_name, ['Sem', 'Fenna'], true))->take(10));
        $veldAbonnees = $veld->take(6);

        $dezeMaand = now()->startOfMonth();

        foreach ($keeperAbonnees as $i => $speler) {
            $open = in_array($i, [1, 5], true); // Fenna en nog één: open en achterstallig
            $this->abonnement($speler, $aanbod['keepers'], 5950, $dezeMaand, $open ? PaymentStatus::Open : PaymentStatus::Paid, $i);
        }

        foreach ($veldAbonnees as $i => $speler) {
            $this->abonnement($speler, $aanbod['veld'], 6500, $dezeMaand, PaymentStatus::Paid, 20 + $i);
        }

        // Eén mislukte incasso van vorige maand bij een veldspeler die (nog)
        // geen abonnement heeft: € 65,00 openstaand.
        $mislukt = $veld[6];
        Payment::create([
            'player_id' => $mislukt->id,
            'amount_cents' => 6500,
            'vat_rate' => 9,
            'status' => PaymentStatus::Failed,
            'method' => PaymentMethod::DirectDebit,
            'description' => 'Techniektraining '.now()->subMonth()->translatedFormat('F Y'),
            'due_on' => now()->subMonth()->startOfMonth()->addDays(6)->toDateString(),
        ]);

        // Herfstkamp: vijf betaald, één aanbetaling nog open (€ 96,00).
        foreach ($keepers->slice(12, 5)->values() as $k => $speler) {
            $this->aankoop($speler, $aanbod['kamp'], 14900, PaymentStatus::Paid, $dezeMaand->copy()->addDays(2 + $k), PaymentMethod::Ideal);
        }
        $this->aankoop($keepers[17], $aanbod['kamp'], 9600, PaymentStatus::Open, $dezeMaand->copy()->addDays(1), null, 'Herfstkamp keepers · aanbetaling');

        // Keepersblok: vijf betaald.
        foreach ($keepers->slice(2, 5)->values() as $k => $speler) {
            $this->aankoop($speler, $aanbod['blok'], 12900, PaymentStatus::Paid, $dezeMaand->copy()->addDays(4 + $k), $k % 2 === 0 ? PaymentMethod::Ideal : PaymentMethod::Transfer);
        }
    }

    protected function abonnement(Player $speler, Product $product, int $bedrag, Carbon $dezeMaand, PaymentStatus $dezeMaandStatus, int $i): void
    {
        $abonnement = Subscription::create([
            'player_id' => $speler->id,
            'product_id' => $product->id,
            'amount_cents' => $bedrag,
            'vat_rate' => 9,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'payment_method' => $i % 4 === 0 ? PaymentMethod::Transfer : PaymentMethod::DirectDebit,
            'starts_on' => $dezeMaand->copy()->subMonths(3)->toDateString(),
        ]);

        foreach ([3, 2, 1, 0] as $terug) {
            $maand = $dezeMaand->copy()->subMonths($terug);
            $status = $terug === 0 ? $dezeMaandStatus : PaymentStatus::Paid;

            Payment::create([
                'player_id' => $speler->id,
                'subscription_id' => $abonnement->id,
                'amount_cents' => $bedrag,
                'vat_rate' => 9,
                'status' => $status,
                'method' => $abonnement->payment_method,
                'description' => $product->name.' '.$maand->translatedFormat('F Y'),
                'period_start' => $maand->toDateString(),
                'due_on' => $maand->copy()->addDays(5)->toDateString(),
                'paid_at' => $status === PaymentStatus::Paid ? $maand->copy()->addDays(2 + $i % 4)->setTime(9, 14) : null,
            ]);
        }
    }

    protected function aankoop(Player $speler, Product $product, int $bedrag, PaymentStatus $status, Carbon $moment, ?PaymentMethod $methode, ?string $omschrijving = null): void
    {
        $aankoop = Purchase::create([
            'player_id' => $speler->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'type' => $product->type,
            'amount_cents' => $bedrag,
            'vat_rate' => 9,
            'credits_used' => 0,
            'starts_on' => $moment->toDateString(),
            'status' => 'active',
        ]);

        Payment::create([
            'player_id' => $speler->id,
            'purchase_id' => $aankoop->id,
            'amount_cents' => $bedrag,
            'vat_rate' => 9,
            'status' => $status,
            'method' => $methode,
            'description' => $omschrijving ?? $product->name,
            'due_on' => $moment->copy()->addDays(7)->toDateString(),
            'paid_at' => $status === PaymentStatus::Paid ? $moment->copy()->setTime(19, 42) : null,
        ]);
    }

    /** Een paar doelen, zodat "op koers" en de achterkant van de kaart iets laten zien. */
    protected function maakDoelen(): void
    {
        $trainer = $this->trainers['jesse'];

        foreach ($this->spelers->where('is_active', true)->values()->take(6) as $i => $speler) {
            $speler->refresh();
            $categorie = $speler->position->categories()[$i % 6];
            $huidig = (int) round(($speler->category_ratings ?? [])[$categorie->value] ?? 70);

            $speler->goals()->create([
                'set_by_id' => $trainer->id,
                'category' => $categorie->value,
                'start_rating' => $huidig,
                'target_rating' => min(95, $huidig + 5),
                'starts_on' => now()->subWeeks(3)->toDateString(),
                'due_on' => now()->addWeeks(6)->toDateString(),
                'note' => 'Elke training vijf minuten extra op dit onderdeel.',
            ]);
        }
    }
}
