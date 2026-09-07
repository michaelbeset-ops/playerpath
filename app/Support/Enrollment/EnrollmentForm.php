<?php

namespace App\Support\Enrollment;

use App\Enums\Feature;
use App\Enums\PaymentMethod;
use App\Enums\PlayerPosition;
use App\Enums\ProductType;
use App\Models\ConsentDocument;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;

/**
 * Wat het openbare inschrijfformulier moet weten om zich op te bouwen.
 *
 * Alles komt uit de inschrijfinstellingen van de school: welke velden er
 * staan en of ze verplicht zijn, welke toestemmingen, welke betaalvormen per
 * aanbod, of er inschrijfgeld of een kledingpakket bij komt. Het formulier
 * zelf bevat geen regels; het tekent wat hier staat.
 *
 * Voor een ingelogde ouder komen naam, e-mail en de kinderen mee, zodat een
 * tweede kind inschrijven geen tweede keer alles intypen is.
 */
class EnrollmentForm
{
    public function __construct(
        protected Features $features,
        protected PaymentGateway $gateway,
    ) {}

    /** @return array<string, mixed> */
    public function for(School $school, EnrollmentSettings $settings, ?User $ouder): array
    {
        $kinderen = $ouder?->isOuder()
            ? $ouder->children()->orderBy('first_name')->get()->map(fn (Player $p) => [
                'id' => $p->id,
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'date_of_birth' => $p->date_of_birth?->format('Y-m-d'),
                'position' => $p->position->value,
                'age' => $p->age,
            ])->values()->all()
            : [];

        return [
            'guardian' => $ouder === null ? null : [
                'name' => $ouder->name,
                'email' => $ouder->email,
                'children' => $kinderen,
            ],
            'fields' => $settings->get('fields'),
            'consents' => array_map(fn (array $doc) => [
                'key' => $doc['key'],
                'title' => $doc['title'],
                'body' => $doc['body'],
                'required' => $doc['required'],
            ], ConsentDocument::allForSchool()),
            'extras' => [
                'registration_fee' => $settings->registrationFeeCents() > 0 ? Money::format($settings->registrationFeeCents()) : null,
                'kit' => $settings->kitCents() > 0 ? Money::format($settings->kitCents()) : null,
                'code' => (bool) ($settings->get('discounts')['code']['enabled'] ?? false),
                'family' => ($settings->get('discounts')['family']['enabled'] ?? false) ? (int) $settings->get('discounts')['family']['percent'] : null,
            ],
            'policy' => [
                'approval' => $settings->approvesManually() ? 'manual' : 'automatic',
                'cancellation' => $settings->get('cancellation'),
                'absence' => $settings->get('absence'),
                'waitlist' => $settings->hasWaitlist(),
                'notice_months' => $settings->noticeMonths(),
            ],
            'paymentMethods' => $this->betaalmethoden($school),
            'positions' => PlayerPosition::options(),
        ];
    }

    /**
     * Eén aanbod zoals een ouder het leest, met zijn betaalvormen.
     *
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'type' => $product->type->label(),
            'type_key' => $product->type->value,
            'is_trial' => $product->type === ProductType::Proefles,
            'amount' => Money::format($product->amount_cents),
            'is_free' => $product->amount_cents === 0,
            'billing' => $product->billing_type->short(),
            'is_subscription' => $product->isRecurring(),
            'starts_on' => $product->starts_on?->translatedFormat('j F Y'),
            'ends_on' => $product->ends_on?->translatedFormat('j F Y'),
            'sessions_count' => $product->sessions_count,
            'location' => $product->location,
            'min_age' => $product->min_age,
            'max_age' => $product->max_age,
            'audience' => $product->audience->value,
            'audience_label' => $product->audience->label(),
            'credits' => $product->type->needsCredits() ? $product->credits : null,
            'spots_left' => $product->spotsLeft(),
            'is_full' => $product->isFull(),
            'payment_options' => $product->paymentOptions->map(fn (PaymentOption $o) => [
                'id' => $o->id,
                'type' => $o->type->value,
                'label' => $o->label ?? $o->type->label(),
                'description' => $o->describe(),
                'is_default' => $o->is_default,
            ])->values()->all(),
        ];
    }

    /**
     * Hoe je betaalt: contant bij de school, of online als er een provider is.
     *
     * @return list<array{value: string, label: string, hint: string, subscription_only: bool}>
     */
    public function betaalmethoden(School $school): array
    {
        if (! $this->features->enabled(Feature::Betalingen, $school)) {
            return [];
        }

        $opties = [[
            'value' => PaymentMethod::Cash->value,
            'label' => 'Bij de school',
            'hint' => 'Contant of per overboeking; de school zet de betaling op ontvangen.',
            'subscription_only' => false,
        ]];

        if (! $this->gateway->isConnected()) {
            return $opties;
        }

        $opties[] = [
            'value' => PaymentMethod::Ideal->value,
            'label' => 'Online met iDEAL',
            'hint' => 'Je krijgt een betaallink per e-mail.',
            'subscription_only' => false,
        ];

        $opties[] = [
            'value' => PaymentMethod::DirectDebit->value,
            'label' => 'Automatische incasso',
            'hint' => 'De eerste betaling doe je zelf via iDEAL; daarna wordt elke termijn afgeschreven.',
            'subscription_only' => true,
        ];

        return $opties;
    }
}
