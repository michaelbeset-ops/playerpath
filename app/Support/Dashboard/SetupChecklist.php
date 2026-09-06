<?php

namespace App\Support\Dashboard;

use App\Enums\Role;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;

/**
 * De drie dingen die een nieuwe school moet doen voordat het product werkt.
 *
 * Onderzoek naar app-onboarding laat zien dat meer dan negentig procent van de
 * gebruikers een instroom nooit afmaakt, en dat het terugbrengen van zeven
 * schermen naar drie de uitval bijna halveert. Vandaar drie stappen en niet
 * tien, en vandaar een lijstje op het dashboard in plaats van een aparte
 * wizard: je ziet het naast je echte werk staan en kunt het overslaan.
 *
 * De lijst **verdwijnt zodra alles gedaan is** en komt nooit terug. Een
 * checklist die blijft hangen nadat je klaar bent, leert mensen hem negeren.
 */
class SetupChecklist
{
    /**
     * @return array<string, mixed>|null null als de school al draait
     */
    public function for(User $user): ?array
    {
        if (! $user->isEigenaar()) {
            return null;
        }

        $stappen = [
            [
                'key' => 'players',
                'title' => 'Voeg je eerste spelers toe',
                'body' => 'Zonder spelers valt er niets te plannen of te rapporteren.',
                'href' => '/players/create',
                'action' => 'Speler toevoegen',
                'done' => Player::query()->exists(),
            ],
            [
                'key' => 'product',
                'title' => 'Zet je eerste product neer',
                'body' => 'Een abonnement of een rittenkaart. Daarmee kun je afspraken vastleggen en online laten inschrijven.',
                'href' => '/products/create',
                'action' => 'Product toevoegen',
                'done' => Product::query()->exists(),
            ],
            [
                'key' => 'report',
                'title' => 'Vul één rapport in',
                'body' => 'Dit is waar het om draait: na het eerste rapport heeft een speler een kaart en zien ouders wat er gebeurt.',
                'href' => '/reports',
                'action' => 'Naar rapporten',
                'done' => Report::query()->exists(),
            ],
        ];

        $klaar = count(array_filter($stappen, fn (array $stap) => $stap['done']));

        if ($klaar === count($stappen)) {
            return null;
        }

        return [
            'steps' => $stappen,
            'done' => $klaar,
            'total' => count($stappen),
            // Een trainer uitnodigen is geen stap maar een tip: een school met
            // één trainer — de eigenaar zelf — is volstrekt normaal.
            'hasTrainer' => User::ofCurrentSchool()->role(Role::Trainer->value)->exists(),
        ];
    }
}
