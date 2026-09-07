<?php

namespace App\Http\Controllers\Schools;

use App\Enums\BillingType;
use App\Enums\Feature;
use App\Enums\OfferingStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\ConsentDocument;
use App\Models\Product;
use App\Models\School;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Features\Features;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hoe deze school inschrijft en int: de wizard en het overzicht erna.
 *
 * Zes stappen, elk één scherm, elk antwoord zet meteen een instelling. Een
 * nieuwe school loopt ze achter elkaar door; daarna is elke stap los te openen
 * vanuit het overzicht. Er is dus één formulier per onderwerp, geen wizard én
 * een apart instellingenscherm die uit elkaar kunnen lopen.
 *
 * Alleen de eigenaar. Bedragen komen als tekst binnen ("12,50") en gaan via
 * Money::toCents naar centen; ze worden nooit als float bewaard.
 */
class EnrollmentSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;
        $instellingen = EnrollmentSettings::for($school);

        // Nog nooit doorlopen: dan begin je bij stap één, niet bij een
        // overzicht van standaarden waar je nog niets van hebt gezien.
        if (! $instellingen->isCompleted()) {
            return $this->edit($request, 1);
        }

        return Inertia::render('enrollment-settings/Index', [
            'settings' => $this->presenteer($instellingen),
            'consents' => ConsentDocument::allForSchool(),
            'development' => Features::enabledFor($school, Feature::Ontwikkeling),
            'steps' => $this->stappen(),
        ]);
    }

    public function edit(Request $request, int $stap): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);
        abort_unless($stap >= 1 && $stap <= count(EnrollmentSettings::STAPPEN), 404);

        $school = $request->user()->school;
        $instellingen = EnrollmentSettings::for($school);

        return Inertia::render('enrollment-settings/Wizard', [
            'step' => $stap,
            'steps' => $this->stappen(),
            'completed' => $instellingen->isCompleted(),
            'settings' => $this->presenteer($instellingen),
            'consents' => ConsentDocument::allForSchool(),
            'development' => Features::enabledFor($school, Feature::Ontwikkeling),
            'offeringTypes' => array_map(fn (ProductType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ], array_values(array_filter(ProductType::cases(), fn (ProductType $t) => $t !== ProductType::Overig))),
        ]);
    }

    public function update(Request $request, int $stap): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        abort_unless($stap >= 1 && $stap <= count(EnrollmentSettings::STAPPEN), 404);

        $school = $request->user()->school;
        $sleutel = EnrollmentSettings::STAPPEN[$stap - 1];

        $antwoorden = match ($sleutel) {
            'aanbod' => $this->aanbod($request),
            'kosten' => $this->kosten($request),
            'betalen' => $this->betalen($request),
            'annuleren' => $this->annuleren($request),
            'kortingen' => $this->kortingen($request),
            'formulier' => $this->formulier($request, $school),
        };

        EnrollmentSettings::save($school, $antwoorden);

        $laatste = $stap === count(EnrollmentSettings::STAPPEN);
        $wasKlaar = EnrollmentSettings::for($school->refresh())->isCompleted();

        if ($laatste) {
            EnrollmentSettings::complete($school);
        }

        // Tijdens de eerste keer loop je door naar de volgende stap; wie later
        // één instelling wijzigt komt terug op het overzicht.
        if (! $wasKlaar && ! $laatste) {
            return redirect()->route('enrollment-settings.edit', ['stap' => $stap + 1]);
        }

        return redirect()->route('enrollment-settings.index')->with('status', 'Opgeslagen.');
    }

    /** @return array<string, mixed> */
    protected function aanbod(Request $request): array
    {
        $soorten = array_map(fn (ProductType $t) => $t->value, array_filter(ProductType::cases(), fn (ProductType $t) => $t !== ProductType::Overig));

        $validated = $request->validate([
            'offering_types' => ['required', 'array', 'min:1'],
            'offering_types.*' => ['string', Rule::in($soorten)],
            'trial_enabled' => ['required', 'boolean'],
            'trial_amount' => ['nullable', 'string', 'max:20'],
        ], [
            'offering_types.required' => 'Kies minstens één aanbodvorm.',
            'offering_types.min' => 'Kies minstens één aanbodvorm.',
        ]);

        $antwoorden = [
            'offering_types' => array_values(array_unique($validated['offering_types'])),
            'trial' => [
                'enabled' => (bool) $validated['trial_enabled'],
                'amount_cents' => $this->centen($validated['trial_amount'] ?? null),
            ],
        ];

        $this->proefles($antwoorden);

        return $antwoorden;
    }

    /**
     * De proefles is een aanbod dat de school niet zelf hoeft aan te maken:
     * staat hij aan, dan bestaat hij, met de prijs uit de instellingen. Uit,
     * dan wordt hij onzichtbaar; wat er al is afgenomen blijft staan.
     *
     * @param  array<string, mixed>  $antwoorden
     */
    protected function proefles(array $antwoorden): void
    {
        $aan = $antwoorden['trial']['enabled'] && in_array(ProductType::Proefles->value, $antwoorden['offering_types'], true);
        $product = Product::query()->where('type', ProductType::Proefles->value)->first();

        if (! $aan) {
            $product?->update(['is_active' => false]);

            return;
        }

        $product ??= Product::create([
            'name' => 'Proefles',
            'description' => 'Eén keer meetrainen om te kijken of het bevalt.',
            'type' => ProductType::Proefles,
            'billing_type' => BillingType::Eenmalig,
            'amount_cents' => 0,
            'vat_rate' => 9,
            'status' => OfferingStatus::Open,
        ]);

        $product->update(['is_active' => true]);
        $product->syncPaymentOptions([['type' => 'eenmalig', 'amount_cents' => $antwoorden['trial']['amount_cents']]]);
    }

    /** @return array<string, mixed> */
    protected function kosten(Request $request): array
    {
        $validated = $request->validate([
            'registration_fee_enabled' => ['required', 'boolean'],
            'registration_fee_amount' => ['nullable', 'string', 'max:20'],
            'kit_enabled' => ['required', 'boolean'],
            'kit_amount' => ['nullable', 'string', 'max:20'],
        ]);

        return [
            'registration_fee' => [
                'enabled' => (bool) $validated['registration_fee_enabled'],
                'amount_cents' => $this->centen($validated['registration_fee_amount'] ?? null),
            ],
            'kit' => [
                'enabled' => (bool) $validated['kit_enabled'],
                'amount_cents' => $this->centen($validated['kit_amount'] ?? null),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function betalen(Request $request): array
    {
        $validated = $request->validate([
            'default_payment_type' => ['required', Rule::in(EnrollmentSettings::BETAALVORMEN)],
            'installments' => ['required', 'integer', 'min:2', 'max:12'],
            'installment_interval' => ['required', Rule::in(['month', 'week'])],
            'auto_renew_block' => ['required', 'boolean'],
            'notice_months' => ['required', 'integer', 'min:0', 'max:12'],
            'approval' => ['required', Rule::in(['manual', 'automatic'])],
            'chargeback_fee_enabled' => ['required', 'boolean'],
            'chargeback_fee_amount' => ['nullable', 'string', 'max:20'],
        ], [], [
            'installments' => 'het aantal termijnen',
            'notice_months' => 'de opzegtermijn',
        ]);

        return [
            'default_payment' => [
                'type' => $validated['default_payment_type'],
                'installments' => (int) $validated['installments'],
                'interval' => $validated['installment_interval'],
            ],
            'auto_renew_block' => (bool) $validated['auto_renew_block'],
            'notice_months' => (int) $validated['notice_months'],
            'approval' => $validated['approval'],
            'chargeback_fee' => [
                'enabled' => (bool) $validated['chargeback_fee_enabled'],
                'amount_cents' => $this->centen($validated['chargeback_fee_amount'] ?? null),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function annuleren(Request $request): array
    {
        $validated = $request->validate([
            'free_until_days' => ['required', 'integer', 'min:0', 'max:365'],
            'retain_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'absence' => ['required', Rule::in(EnrollmentSettings::AFWEZIGHEID)],
        ], [], [
            'free_until_days' => 'het aantal dagen',
            'retain_percent' => 'het percentage',
        ]);

        return [
            'cancellation' => [
                'free_until_days' => (int) $validated['free_until_days'],
                'retain_percent' => (int) $validated['retain_percent'],
            ],
            'absence' => $validated['absence'],
        ];
    }

    /** @return array<string, mixed> */
    protected function kortingen(Request $request): array
    {
        $validated = $request->validate([
            'family_enabled' => ['required', 'boolean'],
            'family_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'early_enabled' => ['required', 'boolean'],
            'early_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'early_days_before' => ['required', 'integer', 'min:1', 'max:365'],
            'volume_enabled' => ['required', 'boolean'],
            'volume_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'volume_from_count' => ['required', 'integer', 'min:2', 'max:20'],
            'code_enabled' => ['required', 'boolean'],
            'stackable' => ['required', 'boolean'],
        ]);

        return [
            'discounts' => [
                'family' => ['enabled' => (bool) $validated['family_enabled'], 'percent' => (int) $validated['family_percent']],
                'early' => [
                    'enabled' => (bool) $validated['early_enabled'],
                    'percent' => (int) $validated['early_percent'],
                    'days_before' => (int) $validated['early_days_before'],
                ],
                'volume' => [
                    'enabled' => (bool) $validated['volume_enabled'],
                    'percent' => (int) $validated['volume_percent'],
                    'from_count' => (int) $validated['volume_from_count'],
                ],
                'code' => ['enabled' => (bool) $validated['code_enabled']],
                'stackable' => (bool) $validated['stackable'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function formulier(Request $request, School $school): array
    {
        $velden = array_keys(EnrollmentSettings::STANDAARD['fields']);
        $soorten = array_keys(ConsentDocument::SOORTEN);

        $validated = $request->validate([
            'waitlist' => ['required', 'boolean'],
            'pay_on_placement' => ['required', 'boolean'],
            'fields' => ['required', 'array'],
            ...collect($velden)->mapWithKeys(fn ($v) => ["fields.{$v}" => ['required', Rule::in(EnrollmentSettings::VELD_STANDEN)]])->all(),
            'consents' => ['required', 'array'],
            ...collect($soorten)->mapWithKeys(fn ($k) => [
                "consents.{$k}.required" => ['required', 'boolean'],
                "consents.{$k}.title" => ['required', 'string', 'max:120'],
                "consents.{$k}.body" => ['required', 'string', 'max:5000'],
            ])->all(),
            'development' => ['required', 'boolean'],
        ], [], [
            'consents.*.title' => 'de titel',
            'consents.*.body' => 'de tekst',
        ]);

        foreach ($soorten as $key) {
            $c = $validated['consents'][$key];
            ConsentDocument::put($key, $c['title'], $c['body'], (bool) $c['required']);
        }

        // De ontwikkelingslaag is een functie van de school (Feature), geen
        // losse instelling: het menu, de routes en de taken lezen die al.
        $school->update([
            'features' => array_replace($school->features ?? [], [Feature::Ontwikkeling->value => (bool) $validated['development']]),
        ]);

        return [
            'capacity' => [
                'waitlist' => (bool) $validated['waitlist'],
                'pay_on_placement' => (bool) $validated['pay_on_placement'],
            ],
            'fields' => collect($velden)->mapWithKeys(fn ($v) => [$v => $validated['fields'][$v]])->all(),
        ];
    }

    protected function centen(?string $bedrag): int
    {
        return $bedrag === null || trim($bedrag) === '' ? 0 : max(0, Money::toCents($bedrag));
    }

    /**
     * De instellingen zoals het scherm ze wil: centen ook als bedrag in tekst,
     * zodat het formulier "12,50" kan tonen en de school dat zo terugstuurt.
     *
     * @return array<string, mixed>
     */
    protected function presenteer(EnrollmentSettings $instellingen): array
    {
        $alles = $instellingen->all();
        $euro = fn (int $centen) => $centen === 0 ? '' : number_format($centen / 100, 2, ',', '');

        $alles['trial']['amount'] = $euro($alles['trial']['amount_cents']);
        $alles['registration_fee']['amount'] = $euro($alles['registration_fee']['amount_cents']);
        $alles['kit']['amount'] = $euro($alles['kit']['amount_cents']);
        $alles['chargeback_fee']['amount'] = $euro($alles['chargeback_fee']['amount_cents']);

        $alles['trial']['formatted'] = Money::format($alles['trial']['amount_cents']);
        $alles['registration_fee']['formatted'] = Money::format($alles['registration_fee']['amount_cents']);
        $alles['kit']['formatted'] = Money::format($alles['kit']['amount_cents']);
        $alles['chargeback_fee']['formatted'] = Money::format($alles['chargeback_fee']['amount_cents']);

        $alles['offering_labels'] = array_values(array_map(fn (ProductType $t) => $t->label(), array_filter(
            $instellingen->offeringTypes(),
            fn (ProductType $t) => $t !== ProductType::Overig,
        )));

        return $alles;
    }

    /** @return list<array{number: int, key: string, title: string, hint: string}> */
    protected function stappen(): array
    {
        $titels = [
            'aanbod' => ['Aanbod', 'Wat je aanbiedt, en of er een proefles is'],
            'kosten' => ['Kosten erbij', 'Inschrijfgeld en kledingpakket'],
            'betalen' => ['Betalen', 'Vooraf, in termijnen of per maand; verlengen en opzeggen'],
            'annuleren' => ['Annuleren', 'Wat er terugkomt bij annuleren of ziekte'],
            'kortingen' => ['Kortingen', 'Gezin, vroegboek, volume en codes'],
            'formulier' => ['Formulier', 'Wachtlijst, verplichte velden, toestemmingen'],
        ];

        return collect(EnrollmentSettings::STAPPEN)->values()->map(fn (string $key, int $i) => [
            'number' => $i + 1,
            'key' => $key,
            'title' => $titels[$key][0],
            'hint' => $titels[$key][1],
        ])->all();
    }
}
