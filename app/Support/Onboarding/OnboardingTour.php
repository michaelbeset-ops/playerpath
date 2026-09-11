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
 * Vier regels voor de tekst:
 *
 * 1. **Zeg wat je ziet en wat je ermee doet.** Geen uitleg van het product,
 *    maar van dít scherm: "bovenaan staan vier cijfers", "hier vink je af".
 * 2. **Elke stap heeft een tip om zelf iets te proberen.** Het scherm blijft
 *    tijdens de rondleiding gewoon te gebruiken; de tip zegt wat de moeite is.
 * 3. **Geen vaktaal.** Geen "XP", "trend" of "signaal" zonder dat er staat wat
 *    het in gewone woorden is. De eigenaar van een keepersschool is geen
 *    softwaregebruiker van beroep.
 * 4. **Alleen schermen die bestaan.** Staat Betalingen uit, dan is er geen stap
 *    Financiën. Een stap naar een 404 is erger dan geen stap.
 */
class OnboardingTour
{
    public function __construct(protected Features $features) {}

    /**
     * @return list<array{key: string, url: string, anchor: string|null, title: string, body: string, tip: string|null}>
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
                'body' => 'Hier plan je trainingen, beoordelen je trainers de spelers, en zien ouders hoe hun kind vooruitgaat. In veertien korte stappen laten we zien waar alles staat.',
                'tip' => 'Je kunt tijdens de rondleiding gewoon klikken en scrollen. Dit kaartje blijft staan tot je op Volgende drukt, en met het pijltje klap je het even weg.',
            ],
            [
                'key' => 'dashboard',
                'url' => '/dashboard',
                'anchor' => 'dashboard',
                'title' => 'Je startpagina',
                'body' => 'Bovenaan staan vier cijfers: hoeveel spelers je hebt, hoe goed ze gemiddeld scoren, hoeveel rapporten er deze week zijn ingevuld en wat je deze maand hebt verdiend.',
                'tip' => 'Scroll naar beneden. Daar zie je wie het hardst groeit en welke trainingen eraan komen.',
            ],
            [
                'key' => 'aandacht',
                'url' => '/dashboard',
                'anchor' => 'attention',
                'title' => 'Wat er nu van je gevraagd wordt',
                'body' => 'In dit blok staat wat aandacht nodig heeft: een betaling die mislukte, een speler die lang geen rapport kreeg, een training zonder trainer. Achter elke regel zit een knop naar de plek waar je het oplost.',
                'tip' => 'Is er niets aan de hand, dan staat hier één regel: alles loopt. Nu staat er voorbeelddata in, dus je ziet misschien al iets.',
            ],
            [
                'key' => 'agenda',
                'url' => '/calendar',
                'anchor' => 'calendar',
                'title' => 'Je agenda',
                'body' => 'Alle trainingen in een kalender. Een nieuwe training plan je met de plusknop: je kiest een groep, een tijd, een plek en een trainer. Trainers zien hier hun eigen rooster; ouders alleen de trainingen van hun kind.',
                'tip' => 'Klik eens op een training in de kalender om te zien wat erin staat.',
            ],
            [
                'key' => 'trainingen',
                'url' => $training ? '/trainings/'.$training->id : '/trainings',
                'anchor' => 'attendance',
                'title' => 'Wie was erbij',
                'body' => 'Dit is één training. Onderaan staat de groep: na afloop vinkt de trainer af wie er was. Ouders kunnen vooraf laten weten dat hun kind niet komt, dan staat dat er al bij.',
                'tip' => 'Aanwezig zijn levert een speler punten op. Die punten zie je straks terug op zijn spelerskaart.',
            ],
            [
                'key' => 'klanten',
                'url' => '/clients',
                'anchor' => 'clients',
                'title' => 'Je klanten',
                'body' => 'Elke speler, met daaronder zijn ouders. Hier voeg je spelers toe, zet je ze in een groep en nodig je ouders uit voor hun eigen inlog.',
                'tip' => 'Klik op een speler: dan zie je alles over hem op één pagina, van rapporten tot betalingen.',
            ],
            [
                'key' => 'rapport',
                'url' => $training ? '/trainings/'.$training->id.'/rapporten' : '/reports',
                'anchor' => 'quick-report',
                'title' => 'Een rapport invullen',
                'body' => 'Dit is het belangrijkste scherm van de app. Na een training geeft de trainer elke speler een cijfer op zes onderdelen, met een schuifje. De cijfers van de vorige keer staan al ingevuld; hij past alleen aan wat veranderde. Opslaan gaat meteen door naar de volgende speler.',
                'tip' => 'Probeer het: schuif een cijfer omhoog. Zolang je niet op Opslaan drukt, verandert er niets.',
            ],
            [
                'key' => 'kaart',
                'url' => $speler ? '/players/'.$speler->id.'/card' : '/clients',
                'anchor' => 'player-card',
                'title' => 'De spelerskaart',
                'body' => 'Dit is wat een kind en zijn ouders zien. Het grote cijfer is het gemiddelde van de laatste drie rapporten. Door te komen trainen en beter te worden verdient een speler punten, en daarmee stijgt hij van brons naar zilver, goud en elite.',
                'tip' => 'Scroll naar beneden voor "Hoe werkt mijn rating?". Die uitleg zien ouders ook, zodat je die vraag niet zelf hoeft te beantwoorden.',
            ],
            [
                'key' => 'ouder',
                'url' => '/onboarding/ouderweergave',
                'anchor' => 'family',
                'title' => 'Wat een ouder ziet',
                'body' => 'Zo ziet een ouder de app: de eerstvolgende trainingen, het kaartje van zijn kind en wat er nog betaald moet worden. Geen instellingen, niets in te vullen. Dit is wat je laat zien als je ouders wilt overtuigen.',
                'tip' => 'Dit is een voorbeeld, met de voorbeeldspelers als kinderen. Een echte ouder ziet alleen zijn eigen kind.',
            ],
        ];

        if ($this->features->enabled(Feature::Inschrijvingen, $school)) {
            $stappen[] = [
                'key' => 'inschrijven',
                'url' => '/enrollments',
                'anchor' => 'enrollments',
                'title' => 'Aanmeldingen',
                'body' => 'Elke school krijgt een eigen aanmeldpagina. Daar ziet een ouder je aanbod — blokken, abonnementen, kampen — en meldt hij zijn kind aan. Hier komen die aanmeldingen binnen en keur je ze goed. Na je goedkeuring krijgt de ouder een betaalverzoek.',
                'tip' => 'Je aanbod zelf, met prijzen en data, beheer je bovenin onder Mijn bedrijf → Aanbod.',
            ];
        }

        if ($this->features->enabled(Feature::Betalingen, $school)) {
            $stappen[] = [
                'key' => 'financien',
                'url' => '/payments',
                'anchor' => 'payments',
                'title' => 'Je geld',
                'body' => 'In één oogopslag: wat er binnenkwam, wat nog openstaat en wat te laat is. Bij elke openstaande rekening staan de naam en het telefoonnummer, zodat je weet wie je moet bellen.',
                'tip' => 'Onder Overzichten download je alles in één bestand voor je boekhouder.',
            ];
        }

        if ($this->features->enabled(Feature::Mededelingen, $school)) {
            $stappen[] = [
                'key' => 'mededelingen',
                'url' => '/announcements',
                'anchor' => 'announcements',
                'title' => 'Berichten sturen',
                'body' => 'Een bericht naar alle ouders, of alleen naar één groep. Het komt in de app én per e-mail aan. Zeg je een training af, dan krijgen de ouders van die groep vanzelf bericht.',
                'tip' => 'Je trainers kunnen ook een bericht sturen, bijvoorbeeld als het veld onder water staat.',
            ];
        }

        $stappen[] = [
            'key' => 'bedrijf',
            'url' => '/staff',
            'anchor' => 'business',
            'title' => 'Mijn bedrijf',
            'body' => 'Alles over je school zelf: je trainers, je locaties, je logo en kleur, en hoe je inschrijft en int. Je vindt het bovenin onder Mijn bedrijf.',
            'tip' => 'Hier nodig je ook je trainers uit. Zij krijgen een mail en kiezen zelf een wachtwoord.',
        ];

        $stappen[] = [
            'key' => 'afsluiting',
            'url' => '/dashboard',
            'anchor' => null,
            'title' => 'Dat was de rondleiding',
            'body' => 'Alles wat je zag was voorbeelddata; die ruimen we straks voor je op. Nu richt je in een paar stappen je eigen school in: naam en logo, je aanbod, en wie er training geeft. Alles kun je later nog aanpassen.',
            'tip' => 'Wil je de rondleiding nog eens zien? Klik bovenin op het vraagteken.',
        ];

        return $stappen;
    }
}
