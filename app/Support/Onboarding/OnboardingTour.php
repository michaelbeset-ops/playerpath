<?php

namespace App\Support\Onboarding;

use App\Enums\Feature;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Support\Features\Features;

/**
 * De rondleiding: veertien schermen, één uitleg per scherm.
 *
 * Hij loopt door de echte app, niet door plaatjes ervan: elke stap heeft een
 * adres en een anker op dat scherm, en de voorbeelddata zorgt dat er iets te
 * zien is. Vandaar dat de stappen hier op de server worden opgebouwd en niet in
 * de browser — het adres van "een gevulde spelerskaart" is het adres van een
 * echte voorbeeldspeler, en welke functies deze school heeft bepaalt welke
 * stappen er überhaupt zijn.
 *
 * Drie regels:
 *
 * 1. **Twee of drie zinnen per stap.** Een tour die langer leest dan het
 *    uitproberen zelf wordt weggeklikt.
 * 2. **Alleen schermen die bestaan.** Staat Betalingen uit, dan is er geen stap
 *    Financiën. Een stap naar een 404 is erger dan geen stap.
 * 3. **Zonder voorbeelddata ook bruikbaar.** Dan wijzen de spelerstappen naar
 *    de lijst in plaats van naar één kaart; de tekst blijft kloppen.
 */
class OnboardingTour
{
    public function __construct(protected Features $features) {}

    /**
     * @return list<array{key: string, url: string, anchor: string|null, title: string, body: string}>
     */
    public function steps(School $school): array
    {
        // Bewust niet via Tenancy::set(): dit wordt ook uit het platformbeheer
        // aangeroepen, en daar mag de scope niet dichtklappen. Expliciet op
        // school_id, net als DeleteSchool::summarise().
        $spelers = Player::withoutSchoolScope()->where('school_id', $school->id)->whereNotNull('overall_rating');
        $speler = (clone $spelers)->where('is_demo', true)->orderBy('first_name')->first()
            ?? $spelers->orderBy('first_name')->first();

        $trainingen = Training::withoutSchoolScope()->where('school_id', $school->id);
        $training = (clone $trainingen)->where('is_demo', true)->orderBy('starts_at')->first()
            ?? $trainingen->orderByDesc('starts_at')->first();

        $stappen = [
            [
                'key' => 'welkom',
                'url' => '/dashboard',
                'anchor' => null,
                'title' => 'Welkom bij PlayerPath',
                'body' => 'Een trainer vult in een halve minuut een rapport in, en de spelerskaart van het kind verandert zichtbaar mee. Ouders zien groei; jij ziet je hele school in één oogopslag. Deze rondleiding loopt langs elk onderdeel — in veertien korte stappen.',
            ],
            [
                'key' => 'dashboard',
                'url' => '/dashboard',
                'anchor' => 'dashboard',
                'title' => 'Je dashboard',
                'body' => 'Hoe staat je school ervoor: spelers, rating, rapporten en omzet, met de trend erbij. Je kunt de onderdelen zelf schikken; op een telefoon staat er minder, zodat het in één blik past.',
            ],
            [
                'key' => 'aandacht',
                'url' => '/dashboard',
                'anchor' => 'attention',
                'title' => 'Wat er nu actie vraagt',
                'body' => 'Mislukte betalingen, spelers zonder recent rapport, een training zonder trainer. Elk signaal heeft een knop naar de plek waar je het oplost. Is er niets, dan staat er één rustige regel.',
            ],
            [
                'key' => 'agenda',
                'url' => '/calendar',
                'anchor' => 'calendar',
                'title' => 'Je agenda',
                'body' => 'Trainingen plannen, met een trainer en een locatie erbij. Trainers zien hier hun eigen rooster; ouders alleen de groep van hun kind.',
            ],
            [
                'key' => 'trainingen',
                'url' => $training ? '/trainings/'.$training->id : '/trainings',
                'anchor' => 'attendance',
                'title' => 'Aanwezigheid afvinken',
                'body' => 'Bij elke training staat de groep klaar om af te vinken. Ouders kunnen vooraf afmelden; de trainer vinkt achteraf af. Aanwezigheid levert een speler XP op.',
            ],
            [
                'key' => 'klanten',
                'url' => '/clients',
                'anchor' => 'clients',
                'title' => 'Je klanten',
                'body' => 'Spelers met hun ouders eronder — één lijst, want je denkt in een kind met iemand erbij die je belt. Hier koppel je ouders, deel je spelers in groepen en nodig je uit.',
            ],
            [
                'key' => 'rapport',
                'url' => $training ? '/trainings/'.$training->id.'/rapporten' : '/reports',
                'anchor' => 'quick-report',
                'title' => 'Rapport invullen in dertig seconden',
                'body' => 'Zes schuiven, de cijfers van vorige keer staan al ingevuld, en "Opslaan & volgende" gaat meteen door naar de volgende speler. Dit is het hart van het product: hier ontstaat de kaart.',
            ],
            [
                'key' => 'kaart',
                'url' => $speler ? '/players/'.$speler->id.'/card' : '/clients',
                'anchor' => 'player-card',
                'title' => 'De spelerskaart',
                'body' => 'De laatste drie rapporten worden gemiddeld en maal tien: een 7,5 leest als 75. Aanwezig zijn en groeien levert XP op, XP brengt een speler naar brons, zilver, goud en elite, en onderweg haalt hij mijlpalen.',
            ],
            [
                'key' => 'ouder',
                'url' => '/onboarding/ouderweergave',
                'anchor' => 'family',
                'title' => 'Wat een ouder ziet',
                'body' => 'Dit is jouw verkoopargument. Een ouder ziet wanneer de training is, de kaart en de groei van zijn kind, en wat er nog openstaat. Geen instellingen, niets in te vullen.',
            ],
        ];

        if ($this->features->enabled(Feature::Inschrijvingen, $school)) {
            $stappen[] = [
                'key' => 'inschrijven',
                'url' => '/enrollments',
                'anchor' => 'enrollments',
                'title' => 'Inschrijvingen en aanbod',
                'body' => 'Elke school heeft een eigen aanmeldpagina met haar aanbod: blokken, abonnementen, kampen. Een ouder meldt zich daar aan, jij keurt goed, en de ouder krijgt een betaalverzoek.',
            ];
        }

        if ($this->features->enabled(Feature::Betalingen, $school)) {
            $stappen[] = [
                'key' => 'financien',
                'url' => '/payments',
                'anchor' => 'payments',
                'title' => 'Financiën',
                'body' => 'Wat er binnenkwam, wat openstaat en wat te laat is — met wie je moet bellen. Overzichten voor de boekhouder staan er naast.',
            ];
        }

        if ($this->features->enabled(Feature::Mededelingen, $school)) {
            $stappen[] = [
                'key' => 'mededelingen',
                'url' => '/announcements',
                'anchor' => 'announcements',
                'title' => 'Mededelingen',
                'body' => 'Een bericht naar de hele school of naar één groep, in de app en per e-mail. Een training afzeggen stuurt vanzelf een bericht naar de ouders van die groep.',
            ];
        }

        $stappen[] = [
            'key' => 'bedrijf',
            'url' => '/staff',
            'anchor' => 'business',
            'title' => 'Mijn bedrijf',
            'body' => 'Je personeel, je locaties, je huisstijl en hoe je inschrijft en int. Alles wat over de school zelf gaat en niet over een klant.',
        ];

        $stappen[] = [
            'key' => 'afsluiting',
            'url' => '/dashboard',
            'anchor' => null,
            'title' => 'Nu gaan we jouw school inrichten',
            'body' => 'Wat je zag was voorbeelddata. Hierna vul je in een paar stappen je eigen school in: naam en logo, je aanbod, hoe je int, en wie er training geeft. Alles is later aan te passen.',
        ];

        return $stappen;
    }
}
