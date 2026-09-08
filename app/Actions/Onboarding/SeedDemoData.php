<?php

namespace App\Actions\Onboarding;

use App\Enums\BillingType;
use App\Enums\OfferingStatus;
use App\Enums\ProductType;
use App\Enums\ReportCategory;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\Location;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Onboarding\OnboardingState;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Een nieuwe school begint niet met een leeg scherm.
 *
 * Een lege omgeving is de vijand. Wie voor het eerst inlogt en overal "nog
 * niets" ziet staan, weet niet wat het product doet en gaat het ook niet
 * uitzoeken. Daarom staat er vanaf de eerste seconde iets dat wérkt: vier
 * spelers met gevulde kaarten en zichtbare groei, twee trainingen in de agenda,
 * een aanbod en een bericht.
 *
 * Vier regels die dit eerlijk houden:
 *
 * 1. **Alles is gemarkeerd als voorbeeld** (`is_demo`), overal zichtbaar, en
 *    gaat er met één knop in één keer uit. Voorbeelddata die je niet herkent en
 *    niet kunt weghalen is erger dan een leeg scherm — dan staat er straks een
 *    verzonnen kind in je ledenbestand.
 * 2. **Het zijn echte rijen in de echte tabellen.** Geen aparte demo-modus die
 *    elk scherm moet samenvoegen: de kaart wordt echt doorgerekend, de agenda
 *    is echt de agenda. Wat je ziet is wat het product doet.
 * 3. **De rapporten lopen in de tijd op**, zodat er groei te zien is. Vier
 *    identieke rapporten geven een vlakke lijn en dan lijkt het product stuk.
 * 4. **De startchecklist telt dit niet mee.** Anders is je school "af" zonder
 *    dat je één echte speler hebt toegevoegd; zie `SetupChecklist`.
 *
 * De namen zijn bewust neutraal-Nederlands en de kinderen zijn duidelijk
 * verzonnen. Er komt geen foto bij: een gezicht van een kind erbij verzinnen is
 * precies wat je in een product over kinderen niet doet. De kaart valt dan
 * terug op initialen, en dat is wat een school in het begin toch ziet.
 */
class SeedDemoData
{
    public function __construct(
        protected Tenancy $tenancy,
        protected CalculatePlayerCard $calculator,
    ) {}

    /** De vier voorbeeldspelers: twee keepers, twee veldspelers, vier leeftijden. */
    protected const SPELERS = [
        ['Sam', 'de Boer', 'keeper', 9, [6.5, 7.0, 7.4]],
        ['Noor', 'Visser', 'field', 11, [7.0, 7.2, 7.8]],
        ['Youssef', 'El Amrani', 'keeper', 13, [7.5, 7.4, 8.1]],
        ['Lieke', 'Jansen', 'field', 15, [6.0, 6.6, 6.9]],
    ];

    public function handle(School $school, ?User $trainer = null): void
    {
        // Twee keer neerzetten zou acht spelers opleveren waarvan je er vier
        // niet herkent. Eén keer, bij het aanmaken van de school.
        if (OnboardingState::for($school)->has('demo_seeded_at')) {
            return;
        }

        $this->tenancy->set($school);

        $trainer ??= $school->users()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['eigenaar', 'trainer']))
            ->first();

        DB::transaction(function () use ($school, $trainer) {
            $locatie = $this->locatie();
            $groep = $this->groep();
            $spelers = $this->spelers($groep);

            $this->trainingen($groep, $locatie, $trainer);
            $this->aanbod($locatie);
            $this->mededeling($school);

            foreach ($spelers as [$speler, $cijfers]) {
                $this->rapporten($speler, $trainer, $cijfers);
            }
        });

        OnboardingState::mark($school, 'demo_seeded_at');
    }

    /**
     * Een voorbeeldrij wegschrijven.
     *
     * Via forceFill en niet via create(): `is_demo` staat bewust in geen enkel
     * `$fillable`. Zou het daar wel staan, dan kan een formulier een echte
     * speler als voorbeeld markeren — en die verdwijnt dan zodra iemand op
     * "voorbeelddata verwijderen" drukt.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $waarden
     * @return TModel
     */
    protected function maak(string $model, array $waarden)
    {
        $rij = new $model;
        $rij->forceFill([...$waarden, 'is_demo' => true])->save();

        return $rij;
    }

    protected function locatie(): Location
    {
        return $this->maak(Location::class, [
            'name' => 'Sportpark De Voorbeeldweide',
            'address' => 'Voorbeeldlaan 1, Utrecht',
            'is_active' => true,
        ]);
    }

    protected function groep(): Group
    {
        return $this->maak(Group::class, [
            'name' => 'Voorbeeldgroep',
            'age_category' => 'Onder 12',
            'is_active' => true,
        ]);
    }

    /**
     * @return list<array{0: Player, 1: list<float>}>
     */
    protected function spelers(Group $groep): array
    {
        $uit = [];

        foreach (self::SPELERS as [$voornaam, $achternaam, $positie, $leeftijd, $cijfers]) {
            $speler = $this->maak(Player::class, [
                'first_name' => $voornaam,
                'last_name' => $achternaam,
                // Een verjaardag die niet vandaag is: anders krijgt een verse
                // school meteen een felicitatie voor een verzonnen kind.
                'date_of_birth' => now()->subYears($leeftijd)->subMonths(5)->startOfDay()->toDateString(),
                'position' => $positie,
                'is_active' => true,
            ]);

            $groep->players()->attach($speler->id);

            $uit[] = [$speler, $cijfers];
        }

        return $uit;
    }

    /**
     * Drie rapporten per speler, oplopend in de tijd.
     *
     * De kaart rekent met de laatste drie rapporten, dus met drie stuks staat
     * hij vol en is er groei te zien. Ze liggen op acht, vier en één week terug:
     * ver genoeg uit elkaar om een lijn te tekenen, dichtbij genoeg om als
     * "recent" te tellen.
     *
     * @param  list<float>  $cijfers
     */
    protected function rapporten(Player $speler, ?User $trainer, array $cijfers): void
    {
        $categorieen = ReportCategory::forPosition($speler->position);
        $weken = [8, 4, 1];

        foreach ($cijfers as $index => $basis) {
            $report = $this->maak(Report::class, [
                'player_id' => $speler->id,
                'trainer_id' => $trainer?->id,
                'reported_on' => now()->subWeeks($weken[$index])->toDateString(),
                'note' => $index === count($cijfers) - 1
                    ? 'Voorbeeldrapport. Zo ziet het eruit als een trainer een speler beoordeelt.'
                    : null,
            ]);

            foreach ($categorieen as $positie => $categorie) {
                // Een beetje spreiding over de categorieën, anders staan alle
                // zes de balken op de kaart precies even hoog en lijkt het een
                // plaatje in plaats van een beoordeling.
                $cijfer = round(min(10, max(1, $basis + (($positie % 3) - 1) * 0.4)), 1);

                $report->scores()->create(['category' => $categorie->value, 'score' => $cijfer]);
            }
        }

        $this->calculator->refresh($speler->refresh());
    }

    protected function trainingen(Group $groep, Location $locatie, ?User $trainer): void
    {
        foreach ([2, 9] as $dagen) {
            $training = $this->maak(Training::class, [
                'group_id' => $groep->id,
                'starts_at' => now()->addDays($dagen)->setTime(18, 0),
                'ends_at' => now()->addDays($dagen)->setTime(19, 30),
                'location' => $locatie->name,
                'location_id' => $locatie->id,
            ]);

            if ($trainer !== null) {
                $training->trainers()->attach($trainer->id);
            }
        }
    }

    protected function aanbod(Location $locatie): void
    {
        $this->maak(Product::class, [
            'name' => 'Voorbeeld: keeperstraining per maand',
            'description' => 'Wekelijkse keeperstraining. Dit is een voorbeeld — pas hem aan of haal hem weg.',
            'type' => ProductType::Doorlopend->value,
            'billing_type' => BillingType::Maandelijks->value,
            'amount_cents' => 3250,
            'interval' => 'monthly',
            'vat_rate' => 9,
            'status' => OfferingStatus::Concept->value,
            'location' => $locatie->name,
            'location_id' => $locatie->id,
            'is_active' => true,
        ]);
    }

    /**
     * Eén bericht, en bewust niet verstuurd.
     *
     * Een voorbeeldmededeling die daadwerkelijk de deur uit gaat zou bij een
     * echte school een e-mail aan echte ouders opleveren over iets dat niet
     * bestaat. Hij staat er dus als verstuurd bericht in de lijst, met nul
     * ontvangers — genoeg om te laten zien waar het staat.
     */
    protected function mededeling(School $school): void
    {
        $this->maak(Announcement::class, [
            'author_id' => $school->users()->value('id'),
            'group_id' => null,
            'title' => 'Voorbeeld: welkom bij '.$school->name,
            'body' => "Zo ziet een mededeling eruit. Je kunt hem naar de hele school sturen of naar één groep; ouders en spelers krijgen hem in de app en per e-mail.\n\nDit is een voorbeeldbericht en is naar niemand verstuurd.",
            'recipients_count' => 0,
        ]);
    }
}
