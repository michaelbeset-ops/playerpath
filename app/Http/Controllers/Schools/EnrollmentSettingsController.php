<?php

namespace App\Http\Controllers\Schools;

use App\Actions\Onboarding\RemoveDemoData;
use App\Actions\Schools\ChangeCardMode;
use App\Enums\BillingType;
use App\Enums\Feature;
use App\Enums\OfferingStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ConsentDocument;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Product;
use App\Models\School;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Onboarding\OnboardingState;
use App\Support\Rating\AgeCategory;
use App\Support\Rating\RatingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
    /**
     * Wie je belt als het niet bij je school past. Staat onder de samenvatting:
     * een school die na het instellen denkt "zo werk ik niet" moet niet gaan
     * zoeken, maar iemand aan de lijn krijgen.
     */
    public const SUPPORT_PHONE = '06 43 43 00 03';

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;
        $instellingen = EnrollmentSettings::for($school);

        // Nog nooit doorlopen: dan begin je bij stap één, niet bij een
        // overzicht van standaarden waar je nog niets van hebt gezien.
        if (! $instellingen->isCompleted()) {
            // Waar je was, niet bij stap één: wie halverwege stopte hoort daar
            // te kunnen hervatten.
            // Vers lezen: de gebruiker draagt een school mee van vóór het opslaan.
            $stap = min(count(EnrollmentSettings::STAPPEN), max(1, OnboardingState::for($school->fresh())->wizardStep() + 1));

            return $this->edit($request, $stap);
        }

        return Inertia::render('enrollment-settings/Index', [
            'supportPhone' => self::SUPPORT_PHONE,
            'justCompleted' => (bool) $request->session()->get('wizardCompleted', false),
            'slug' => $school->slug,
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
            // Voor stap één: wie ben je, en waar train je. Niet 'school'
            // noemen: dat is een gedeelde prop, en deze zou hem overschrijven.
            'schoolProfile' => [
                'name' => $school->name,
                'slug' => $school->slug,
                'contact_name' => $school->contact_name,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'brand_color' => $school->brand_color,
                'logo' => $school->logo_path === null ? null : Storage::url($school->logo_path),
                'locations' => Location::orderBy('name')->pluck('name')->all(),
                'domain' => config('app.domain'),
            ],
            // Voor de groepenstap: wat er al is, en de gangbare categorieën.
            'groups' => Group::query()->real()->orderBy('name')->get(['id', 'name', 'age_category'])->map(fn (Group $g) => [
                'id' => $g->id, 'name' => $g->name, 'age_category' => $g->age_category,
            ]),
            'ageCategories' => array_map(fn (int $band) => 'Onder '.$band, AgeCategory::BANDEN),
            // Voor de trainersstap: wie er al is uitgenodigd.
            'invitations' => Invitation::where('role', Role::Trainer->value)->pending()->orderByDesc('created_at')->get()->map(fn (Invitation $rij) => [
                'id' => $rij->id, 'name' => $rij->name, 'email' => $rij->email, 'status' => $rij->status(),
                'expires_on' => $rij->expires_at->format('d-m-Y'), 'sent_count' => $rij->sent_count,
            ]),
            'invitationDays' => (int) ($school->invitation_valid_days ?: 14),
            // De spelerskaart: wat de school koos. Nog niets gekozen: niets
            // voorgekozen, de school kiest zelf tussen de twee.
            'cardMode' => $school->rating_settings['card_mode'] ?? null,
        ]);
    }

    /**
     * Een stap overslaan.
     *
     * Overslaan mag: elke vraag heeft een bruikbare standaard, en wie er nu
     * geen antwoord op heeft moet verder kunnen in plaats van te stoppen. Het
     * scherm zegt erbij dat het later kan. Bij de laatste stap telt overslaan
     * als afronden - anders blijft de wizard eeuwig "nog niet af".
     */
    public function skip(Request $request, int $stap): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        abort_unless($stap >= 1 && $stap <= count(EnrollmentSettings::STAPPEN), 404);

        // De spelerskaart sla je niet over: dat is een keuze die de school zelf
        // maakt, niet een standaard die stilletjes voor haar wordt gekozen.
        if (EnrollmentSettings::STAPPEN[$stap - 1] === 'kaart') {
            return back()->withErrors(['card_mode' => 'Kies een van de twee spelerskaarten.']);
        }

        return $this->volgende($request, $request->user()->school, $stap);
    }

    public function update(Request $request, int $stap): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        abort_unless($stap >= 1 && $stap <= count(EnrollmentSettings::STAPPEN), 404);

        $school = $request->user()->school;
        $sleutel = EnrollmentSettings::STAPPEN[$stap - 1];

        // De eerste stap gaat over de school zelf en niet over inschrijven; hij
        // schrijft rechtstreeks weg en heeft hier verder niets te bewaren.
        if ($sleutel === 'school') {
            $this->schoolgegevens($request, $school);

            return $this->volgende($request, $school, $stap);
        }

        if ($sleutel === 'kaart') {
            $data = $request->validate([
                'card_mode' => ['required', Rule::in([RatingSettings::PRESTATIE, RatingSettings::INZET])],
            ], ['card_mode.required' => 'Kies een spelerskaart.']);

            app(ChangeCardMode::class)->handle($school, $data['card_mode']);

            return $this->volgende($request, $school, $stap);
        }

        if ($sleutel === 'groepen') {
            $this->groepen($request);

            return $this->volgende($request, $school, $stap);
        }

        // Trainers uitnodigen gaat via het uitnodigingsformulier zelf
        // (InvitationController); "verder" is hier hetzelfde als overslaan.
        if ($sleutel === 'trainers') {
            return $this->volgende($request, $school, $stap);
        }

        $antwoorden = match ($sleutel) {
            'aanbod' => $this->aanbod($request),
            'kosten' => $this->kosten($request),
            'betalen' => $this->betalen($request),
            'annuleren' => $this->annuleren($request),
            'kortingen' => $this->kortingen($request),
            'formulier' => $this->formulier($request, $school),
        };

        EnrollmentSettings::save($school, $antwoorden);

        return $this->volgende($request, $school, $stap);
    }

    /** @return array<string, mixed> */
    protected function aanbod(Request $request): array
    {
        $soorten = array_map(fn (ProductType $t) => $t->value, array_filter(ProductType::cases(), fn (ProductType $t) => $t !== ProductType::Overig));

        $validated = $request->validate([
            'offering_types' => ['required', 'array', 'min:1'],
            'offering_types.*' => ['string', Rule::in($soorten)],
            'enrollment_moments' => ['required', 'array', 'min:1'],
            'enrollment_moments.*' => ['string', Rule::in(EnrollmentSettings::INSTAPMOMENTEN)],
            'training_open' => ['required', 'boolean'],
            'training_payment_methods' => ['present', 'array'],
            'training_payment_methods.*' => ['string', Rule::in(['online', 'cash'])],
            'training_requires_approval' => ['required', 'boolean'],
            'trial_enabled' => ['required', 'boolean'],
            'trial_amount' => ['nullable', 'string', 'max:20'],
        ], [
            'offering_types.required' => 'Kies minstens één aanbodvorm.',
            'offering_types.min' => 'Kies minstens één aanbodvorm.',
            'enrollment_moments.required' => 'Kies minstens één moment waarop ouders kunnen inschrijven.',
            'enrollment_moments.min' => 'Kies minstens één moment waarop ouders kunnen inschrijven.',
        ]);

        // Wie "voor een losse training" aanvinkt, wil dat een nieuwe training
        // standaard open staat. Andersom niet: het moment uitzetten laat een
        // bewuste keuze bij de training zelf staan.
        $momenten = array_values(array_unique($validated['enrollment_moments']));
        $losOpen = (bool) $validated['training_open'] || in_array('single_training', $momenten, true);

        $antwoorden = [
            'offering_types' => array_values(array_unique($validated['offering_types'])),
            'enrollment' => ['moments' => $momenten],
            'training_enrollment' => [
                'open' => $losOpen,
                'payment_methods' => array_values(array_unique($validated['training_payment_methods'])) ?: ['cash'],
                'requires_approval' => (bool) $validated['training_requires_approval'],
            ],
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
            'default_payment_types' => ['required', 'array', 'min:1'],
            'default_payment_types.*' => ['string', Rule::in(EnrollmentSettings::BETAALVORMEN)],
            'installments' => ['required', 'integer', 'min:2', 'max:12'],
            'installment_interval' => ['required', Rule::in(['month', 'week'])],
            'auto_renew_block' => ['required', 'boolean'],
            'notice_months' => ['required', 'integer', 'min:0', 'max:12'],
            'approval' => ['required', Rule::in(['manual', 'automatic'])],
            'chargeback_fee_enabled' => ['required', 'boolean'],
            'chargeback_fee_amount' => ['nullable', 'string', 'max:20'],
            'dunning_days' => ['nullable', 'string', 'max:40'],
        ], [
            'default_payment_types.required' => 'Kies minstens één betaalvorm.',
            'default_payment_types.min' => 'Kies minstens één betaalvorm.',
        ], [
            'installments' => 'het aantal termijnen',
            'notice_months' => 'de opzegtermijn',
        ]);

        // Meerdere betaalvormen naast elkaar: de ouder kiest bij het
        // inschrijven. De oude enkelvoudige `type` gaat mee als de eerste,
        // zodat wat er al op leest niet ineens iets anders ziet.
        $vormen = array_values(array_intersect(EnrollmentSettings::BETAALVORMEN, array_unique($validated['default_payment_types'])));

        return [
            'default_payment' => [
                'types' => $vormen,
                'type' => $vormen[0],
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
            'dunning' => ['days' => $this->schema($validated['dunning_days'] ?? null)],
        ];
    }

    /**
     * "3, 7, 14" → [3, 7, 14]: dagen na een mislukte betaling, oplopend, uniek,
     * hooguit vijf. Leeg betekent de standaard.
     *
     * @return list<int>
     */
    protected function schema(?string $invoer): array
    {
        $dagen = collect(preg_split('/[\s,;]+/', (string) $invoer) ?: [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 1 && $d <= 60)
            ->unique()
            ->sort()
            ->values()
            ->take(5)
            ->all();

        return $dagen === [] ? EnrollmentSettings::STANDAARD['dunning']['days'] : $dagen;
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
            'invitation_days' => ['required', 'integer', 'min:1', 'max:30'],
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
                'invitation_days' => (int) $validated['invitation_days'],
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
        $alles['dunning']['text'] = implode(', ', $alles['dunning']['days']);
        $alles['default_payment']['types'] = $instellingen->paymentTypes();
        $alles['enrollment']['moments'] = $instellingen->enrollmentMoments();
        $alles['training_enrollment'] = $instellingen->trainingDefaults();

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
            'school' => ['Je school', 'Naam, logo, je kleur en waar je traint'],
            'aanbod' => ['Inschrijven', 'Wat je aanbiedt, wanneer ouders kunnen instappen, en of er een proefles is'],
            'kosten' => ['Kosten erbij', 'Inschrijfgeld en kledingpakket'],
            'betalen' => ['Betalen', 'Vooraf, in termijnen en/of per maand; goedkeuren, verlengen en opzeggen'],
            'annuleren' => ['Annuleren', 'Wat er terugkomt bij annuleren of ziekte'],
            'kortingen' => ['Kortingen', 'Gezin, vroegboek, volume en codes'],
            'formulier' => ['Formulier', 'Wachtlijst, verplichte velden, toestemmingen'],
            'kaart' => ['Spelerskaart', 'Een kaart met ratings, of een kaart die inzet beloont'],
            'groepen' => ['Groepen', 'In welke groepen je traint, met leeftijdscategorie'],
            'trainers' => ['Trainers', 'Wie er training geeft - ze krijgen een uitnodiging'],
        ];

        return collect(EnrollmentSettings::STAPPEN)->values()->map(fn (string $key, int $i) => [
            'number' => $i + 1,
            'key' => $key,
            'title' => $titels[$key][0],
            'hint' => $titels[$key][1],
        ])->all();
    }

    /**
     * Waar je na een stap heen gaat.
     *
     * Tijdens de eerste keer loop je door naar de volgende stap; wie later één
     * instelling wijzigt komt terug op het overzicht. Eén plek, want de stap
     * over de school heeft precies dezelfde regel.
     */
    protected function volgende(Request $request, School $school, int $stap): RedirectResponse
    {
        $laatste = $stap === count(EnrollmentSettings::STAPPEN);
        $klaar = EnrollmentSettings::for($school->refresh())->isCompleted();

        // Onthouden hoe ver je bent, zodat het menu-item weer opent waar je was.
        if (! $klaar) {
            OnboardingState::save($school, ['wizard_step' => max($stap, OnboardingState::for($school)->wizardStep())]);
        }

        if ($laatste && ! $klaar) {
            EnrollmentSettings::complete($school);

            // De school is ingericht: de voorbeelddata heeft zijn werk gedaan.
            // Hij gaat hier vanzelf weg, zodat er geen verzonnen kind in het
            // ledenbestand blijft staan naast de echte.
            $opgeruimd = $this->ruimVoorbeeldOp($school);

            // Naar de samenvatting, niet naar het dashboard: wie net negen
            // vragen beantwoordde wil in gewone taal lezen wat dat betekent.
            return redirect()->route('enrollment-settings.index')
                ->with('wizardCompleted', true)
                ->with(
                    'status',
                    'Je school is ingericht.'.($opgeruimd ? ' De voorbeelddata is opgeruimd; wat je nu ziet is van jou.' : '')
                );
        }

        if (! $klaar) {
            return redirect()->route('enrollment-settings.edit', ['stap' => $stap + 1]);
        }

        return redirect()->route('enrollment-settings.index')->with('status', 'Opgeslagen.');
    }

    /** De voorbeelddata weg zodra de school is ingericht. */
    protected function ruimVoorbeeldOp(School $school): bool
    {
        if (! OnboardingState::for($school)->hasDemoData()) {
            return false;
        }

        $actie = app(RemoveDemoData::class);
        $actie->handle($school);
        $actie->finish($school);

        return true;
    }

    /**
     * Stap acht: de groepen waarin je traint.
     *
     * Een naam en een leeftijdscategorie per regel. Bestaande groepen blijven
     * staan; alleen wat er nog niet is komt erbij. Een tweede "Keepers O12"
     * naast de eerste zou de agenda splitsen.
     */
    protected function groepen(Request $request): void
    {
        // Een lege regel (de invulregel die het formulier alvast klaarzet) is
        // geen fout maar "geen groep": weglaten, niet weigeren. Anders zit
        // wie nog geen groepen wil iets in te typen om verder te kunnen.
        $request->merge([
            'groups' => array_values(array_filter(
                (array) $request->input('groups', []),
                fn ($rij) => is_array($rij) && trim((string) ($rij['name'] ?? '')) !== '',
            )),
        ]);

        $data = $request->validate([
            'groups' => ['present', 'array', 'max:30'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.age_category' => ['nullable', 'string', 'max:255'],
        ], [], ['groups.*.name' => 'De naam van de groep']);

        foreach ($data['groups'] as $rij) {
            $naam = trim($rij['name']);

            if ($naam === '' || Group::where('name', $naam)->exists()) {
                continue;
            }

            Group::create([
                'name' => $naam,
                'age_category' => $rij['age_category'] ?: null,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Stap één: wie ben je.
     *
     * Naam, logo, merkkleur en de eerste locatie. Meer niet - de rest kan later
     * in de instellingen, en elke vraag die je hier stelt is een vraag waarop
     * iemand kan afhaken voordat hij het product heeft gezien.
     *
     * Het logo en de kleur gaan via dezelfde weg als het huisstijlscherm, zodat
     * er niet twee manieren zijn om hetzelfde te zetten.
     */
    protected function schoolgegevens(Request $request, School $school): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // De slug wordt het adres van de inschrijfpagina en het subdomein:
            // alleen kleine letters, cijfers en streepjes, en uniek over het
            // hele platform. Dezelfde regel als in het beheerscherm.
            'slug' => [
                'required', 'string', 'max:63', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('schools', 'slug')->ignore($school->id),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
        ], [
            'slug.regex' => 'Alleen kleine letters, cijfers en streepjes, bijvoorbeeld keepersschool-rob.',
            'slug.unique' => 'Dit adres is al in gebruik door een andere school.',
            'brand_color.regex' => 'Kies een kleur, bijvoorbeeld #12813D.',
            'logo.image' => 'Kies een afbeelding (jpg, png of webp).',
            'logo.max' => 'Het logo mag hooguit 4 MB zijn.',
        ], [
            'name' => 'De naam van je school',
            'slug' => 'Het adres',
            'contact_email' => 'Het e-mailadres',
            'logo' => 'Het logo',
            'location' => 'De locatie',
        ]);

        $school->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'brand_color' => $data['brand_color'] ?? null,
        ]);

        if ($request->boolean('remove_logo') && $school->logo_path !== null) {
            Storage::disk('public')->delete($school->logo_path);
            $school->forceFill(['logo_path' => null])->save();
        }

        if ($request->hasFile('logo')) {
            if ($school->logo_path !== null) {
                Storage::disk('public')->delete($school->logo_path);
            }

            $school->forceFill([
                'logo_path' => $request->file('logo')->store('logos', 'public'),
            ])->save();
        }

        // Eén locatie is genoeg om te beginnen; de rest zet je op /locaties.
        // Bestaat hij al, dan komt er geen tweede met dezelfde naam bij.
        $naam = trim((string) ($data['location'] ?? ''));

        if ($naam !== '' && Location::where('name', $naam)->doesntExist()) {
            Location::create(['name' => $naam, 'is_active' => true]);
        }
    }
}
