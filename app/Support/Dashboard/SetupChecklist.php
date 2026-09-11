<?php

namespace App\Support\Dashboard;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\Training;
use App\Models\User;
use App\Support\Onboarding\OnboardingState;
use App\Support\Tenancy\Tenancy;

/**
 * Wat een nieuwe school moet doen voordat het product van haar is.
 *
 * Geen klikkerige rondleiding maar een lijst die tot echte handelingen leidt:
 * elke stap is een knop naar de plek waar je hem afmaakt. Onderzoek naar
 * app-onboarding laat zien dat meer dan negentig procent van de gebruikers een
 * instroom nooit afmaakt; wat wél werkt is een lijst die naast je echte werk
 * staat, die je kunt overslaan, en waarvan je ziet hoe ver je bent.
 *
 * Vier regels die deze lijst eerlijk houden:
 *
 * 1. **Voorbeelddata telt niet mee.** Er staan vanaf de eerste seconde vier
 *    spelers en een agenda klaar (zie `SeedDemoData`), maar "voeg je eerste
 *    speler toe" gaat over jóuw eerste speler. Zou het voorbeeld meetellen, dan
 *    is je school af zonder dat je iets hebt gedaan, en heeft de lijst niets
 *    gezegd. Vandaar `real()` overal.
 * 2. **Het eerste rapport is het belangrijkste moment** en staat daarom
 *    gemarkeerd: dat is waar een lege kaart een spelerskaart wordt en een ouder
 *    voor het eerst iets ziet. Zonder dat ene rapport is de rest administratie.
 * 3. **Hij verdwijnt als hij af is**, met één felicitatie, en komt daarna nooit
 *    terug. Een checklist die blijft hangen nadat je klaar bent, leer je negeren.
 * 4. **Wegklikken mag, en is terug te halen.** Anders is de enige manier om van
 *    het blok af te komen: alle zeven stappen doen, ook die je niet wilt.
 */
class SetupChecklist
{
    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @return array<string, mixed>|null null als de school draait of hem wegklikte
     */
    public function for(User $user): ?array
    {
        if (! $user->isEigenaar()) {
            return null;
        }

        $school = $this->tenancy->school() ?? $user->school;
        $stand = OnboardingState::for($school);

        // Ooit helemaal afgerond: dan is de felicitatie geweest.
        if ($stand->checklistCompleted()) {
            return null;
        }

        // Eerst de rondleiding, dan de lijst. Op het eerste scherm stonden
        // anders de voorbeelddata-balk, de rondleiding, het aandacht-blok én
        // deze lijst tegelijk om aandacht te vragen; wie nieuw is weet dan
        // niet waar hij moet beginnen. De rondleiding eindigt bij de wizard,
        // en daarna is dit precies wat er nog te doen is.
        if (! $stand->tourSeen()) {
            return null;
        }

        $stappen = $this->stappen($school);
        $klaar = count(array_filter($stappen, fn (array $stap) => $stap['done']));

        return [
            'steps' => $stappen,
            'done' => $klaar,
            'total' => count($stappen),
            // Af, maar de felicitatie nog niet gezien: het scherm toont hem en
            // meldt dat terug, waarna het blok voorgoed weg is.
            'complete' => $klaar === count($stappen),
            'dismissed' => $stand->checklistDismissed(),
        ];
    }

    /**
     * De vijf praktische stappen die na de wizard overblijven.
     *
     * @return list<array<string, mixed>>
     */
    protected function stappen($school): array
    {
        return [
            [
                'key' => 'player',
                'title' => 'Voeg je eerste speler toe',
                'body' => 'Zonder spelers valt er niets te plannen en niets te beoordelen.',
                'href' => '/players/create',
                'action' => 'Speler toevoegen',
                'done' => Player::query()->real()->exists(),
            ],
            [
                'key' => 'training',
                'title' => 'Plan je eerste training',
                'body' => 'Dan staat hij in de agenda en kun je erna aanwezigheid afvinken.',
                'href' => '/trainings/create',
                'action' => 'Training inplannen',
                'done' => Training::query()->real()->exists(),
            ],
            [
                'key' => 'report',
                // Het moment waar alles op draait, en dus als enige gemarkeerd.
                'highlight' => true,
                'title' => 'Vul je eerste rapport in',
                'body' => 'Dit is waar het om gaat: na dit rapport heeft een speler een kaart, en zien ouders wat er gebeurt.',
                'href' => '/reports',
                'action' => 'Rapport invullen',
                'done' => Report::query()->real()->exists(),
            ],
            [
                'key' => 'guardian',
                'title' => 'Nodig een ouder uit',
                'body' => 'Vanaf dat moment ziet een ouder de kaart van zijn kind. Dat is wat je verkoopt.',
                'href' => '/clients',
                'action' => 'Ouder uitnodigen',
                'done' => $this->uitgenodigd(Role::Ouder),
            ],
            [
                'key' => 'product',
                'title' => 'Maak je eerste aanbod aan',
                'body' => 'Een blok, een abonnement of een kamp. Daarmee kunnen ouders zich online inschrijven.',
                'href' => '/aanbod',
                'action' => 'Aanbod aanmaken',
                'done' => Product::query()->real()->exists(),
            ],
        ];
    }

    /**
     * Is er iemand met deze rol, of staat er een uitnodiging open?
     *
     * Een verstuurde uitnodiging telt als gedaan. Anders blijft de stap open
     * staan totdat iemand anders zijn e-mail leest, en dat is niet iets waar de
     * school nog iets aan kan doen.
     */
    protected function uitgenodigd(Role $rol): bool
    {
        return User::ofCurrentSchool()->role($rol->value)->exists()
            || Invitation::where('role', $rol->value)->exists();
    }
}
