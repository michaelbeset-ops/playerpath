<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Offerings\ScheduleOffering;
use App\Enums\BillingInterval;
use App\Enums\BillingType;
use App\Enums\OfferingStatus;
use App\Enums\PaymentOptionType;
use App\Enums\ProductAudience;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\User;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het **aanbod** van de school: blokken, kampen, doorlopende training,
 * privétraining, small group — en wat er verder verkocht wordt.
 *
 * Eén lijst, geen twee. Een aanbod is tegelijk het ding met een prijs en het
 * ding met data, plekken en trainers; die uit elkaar trekken betekent bij elke
 * vraag nadenken waar een kamp ook alweer staat.
 *
 * Dit kun je volledig inrichten zonder betaalprovider. Dat is precies de
 * bedoeling: eerst je aanbod op orde, dan pas het geld laten lopen.
 *
 * Twee dingen die hier gebeuren en die je niet moet weghalen:
 *
 * 1. **Wat niet bij het soort hoort wordt leeggemaakt.** Een rittenkaart met
 *    een einddatum of een kamp met een maandfrequentie is een veld dat later
 *    niemand meer snapt.
 * 2. **Een blok of kamp krijgt een groep en een rooster** (ScheduleOffering).
 *    Daardoor blijven aanwezigheid, rapporten en de agenda werken zoals ze
 *    altijd al deden.
 */
class ProductController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected ScheduleOffering $rooster,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $producten = Product::query()
            ->with('group')
            ->withCount([
                'subscriptions' => fn ($q) => $q->active(),
                'purchases' => fn ($q) => $q->active(),
                'participations' => fn ($q) => $q->confirmed(),
            ])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->rij($product));

        return Inertia::render('billing/Products', [
            'products' => $producten,
            'types' => $this->typen(),
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
                'message' => $this->gateway->statusMessage(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('billing/ProductForm', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        [$velden, $extra] = $this->valideer($request);

        $product = Product::create($velden);

        $this->naOpslaan($product, $extra);

        return redirect()->route('products.index')->with('status', 'Het aanbod is aangemaakt.');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('billing/ProductForm', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        [$velden, $extra] = $this->valideer($request, $product);

        $product->update($velden);

        $this->naOpslaan($product, $extra);

        return redirect()->route('products.index')->with(
            'status',
            'Het aanbod is opgeslagen. Wat er al is afgenomen houdt zijn oude bedrag.'
        );
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        // Lopende afspraken blijven bestaan met hun eigen bedrag; alleen de
        // verwijzing naar dit aanbod verdwijnt.
        $product->subscriptions()->update(['product_id' => null]);
        $product->purchases()->update(['product_id' => null]);
        $product->delete();

        return redirect()->route('products.index')->with(
            'status',
            'Het aanbod is verwijderd. Wat er al is afgenomen loopt gewoon door.'
        );
    }

    /**
     * Trainers koppelen en, bij een blok of kamp, het rooster leggen.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function naOpslaan(Product $product, array $extra): void
    {
        $product->syncPaymentOptions($extra['payment_options']);
        $product->trainers()->sync($extra['trainers']);

        if (! $product->type->hasSchedule()) {
            return;
        }

        $product->refresh()->load('trainers');

        $this->rooster->handle(
            $product,
            weekdays: $extra['weekdays'],
            dates: $extra['dates'],
            startsAt: $extra['starts_at'],
            endsAt: $extra['ends_at'],
        );
    }

    /** @return array<string, mixed> */
    protected function rij(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'type' => $product->type->value,
            'type_label' => $product->type->label(),
            'billing_type' => $product->billing_type->value,
            'billing_label' => $product->billing_type->short(),
            'amount' => Money::format($product->amount_cents),
            'amount_excl_vat' => Money::format($product->amountExclVatCents()),
            'vat_rate' => $product->vat_rate,
            'credits' => $product->credits,
            'validity_months' => $product->validity_months,
            'interval' => $product->interval?->label(),
            'starts_on' => $product->starts_on?->format('d-m-Y'),
            'ends_on' => $product->ends_on?->format('d-m-Y'),
            'capacity' => $product->capacity,
            'taken' => $product->participations_count ?? 0,
            'is_full' => $product->isFull(),
            'min_age' => $product->min_age,
            'max_age' => $product->max_age,
            'location' => $product->location,
            'status' => $product->status->value,
            'status_label' => $product->status->label(),
            'is_active' => $product->is_active,
            'group_id' => $product->group?->id,
            'subscriptions_count' => $product->subscriptions_count ?? 0,
            'purchases_count' => $product->purchases_count ?? 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function typen(): array
    {
        // Alleen de soorten die deze school gebruikt (inschrijfinstellingen);
        // een bestaand aanbod van een uitgezette soort blijft gewoon staan.
        $soorten = EnrollmentSettings::for(app(Tenancy::class)->school())->offeringTypes();

        return array_map(fn (ProductType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'needs_interval' => $type->needsInterval(),
            'needs_credits' => $type->needsCredits(),
            'has_period' => $type->hasPeriod(),
            'has_schedule' => $type->hasSchedule(),
            'has_capacity' => $type->hasCapacity(),
        ], $soorten);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function valideer(Request $request, ?Product $product = null): array
    {
        $type = ProductType::tryFrom((string) $request->input('type'));

        // De betaalwijze volgt het soort zolang niemand iets anders kiest:
        // doorlopende training is per maand, de rest eenmalig. Zo hoeft een
        // school niet twee keer hetzelfde te zeggen.
        $request->mergeIfMissing([
            'billing_type' => ($type === ProductType::Doorlopend
                ? BillingType::Maandelijks
                : BillingType::Eenmalig)->value,
            'status' => OfferingStatus::Open->value,
            'stops_at_end' => true,
            'audience' => ProductAudience::All->value,
        ]);

        $betaling = BillingType::tryFrom((string) $request->input('billing_type'));
        $schoolId = app(Tenancy::class)->id();

        $validator = validator($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($product?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'billing_type' => ['required', Rule::enum(BillingType::class)],
            // Als tekst binnen, zodat "12,50" net zo goed werkt als "12.50".
            // Money::toCents doet de omzetting; nooit via een float.
            'amount' => ['required', 'string', 'max:20'],
            // Btw-tarieven zoals ze in Nederland bestaan. Sportlessen vallen
            // vaak onder het lage tarief en soms onder een vrijstelling; dat
            // verschilt per school, dus het is een keuze en geen constante.
            'vat_rate' => ['required', 'integer', Rule::in([0, 9, 21])],
            'credits' => [Rule::requiredIf(fn () => $type?->needsCredits() ?? false), 'nullable', 'integer', 'between:1,500'],
            'validity_months' => ['nullable', 'integer', 'between:1,120'],
            'interval' => [
                Rule::requiredIf(fn () => $betaling?->isRecurring() ?? false),
                'nullable', Rule::enum(BillingInterval::class),
            ],
            'starts_on' => [Rule::requiredIf(fn () => $type?->hasPeriod() ?? false), 'nullable', 'date'],
            'ends_on' => [
                Rule::requiredIf(fn () => $type?->hasPeriod() ?? false),
                'nullable', 'date', 'after_or_equal:starts_on',
            ],
            'capacity' => ['nullable', 'integer', 'between:1,500'],
            'min_participants' => ['nullable', 'integer', 'between:1,500', 'lte:capacity'],
            'min_age' => ['nullable', 'integer', 'between:3,99'],
            'max_age' => ['nullable', 'integer', 'between:3,99', 'gte:min_age'],
            'audience' => ['required', Rule::enum(ProductAudience::class)],
            'sessions_count' => ['nullable', 'integer', 'between:1,200'],

            // Extra betaalvormen naast de standaard (billing_type + amount).
            // Elke regel: eenmalig, termijnen (aantal × bedrag) of abonnement.
            'payment_options' => ['nullable', 'array', 'max:5'],
            'payment_options.*.type' => ['required', Rule::enum(PaymentOptionType::class)],
            'payment_options.*.amount' => ['required', 'string', 'max:20'],
            'payment_options.*.installments' => ['nullable', 'integer', 'between:2,12'],
            'payment_options.*.interval' => ['nullable', 'string', Rule::in(['month', 'week', 'monthly', 'quarterly', 'yearly'])],
            'payment_options.*.label' => ['nullable', 'string', 'max:60'],
            'location_id' => [
                'nullable', 'integer',
                Rule::exists('locations', 'id')->where('school_id', $schoolId),
            ],
            'status' => ['required', Rule::enum(OfferingStatus::class)],
            'stops_at_end' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],

            // De trainers bij dit aanbod. Rule::exists gaat buiten de global
            // scope om, dus de school staat er expliciet bij. Zie CLAUDE.md.
            'trainers' => ['nullable', 'array'],
            'trainers.*' => ['integer', Rule::exists('users', 'id')->where('school_id', $schoolId)],

            // Het rooster: een wekelijkse reeks of losse dagen.
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
        ], [
            'name.unique' => 'Er bestaat al een aanbod met deze naam.',
            'credits.required' => 'Vul in hoeveel beurten er op de kaart staan.',
            'interval.required' => 'Kies hoe vaak er per maand in rekening wordt gebracht.',
            'starts_on.required' => 'Vul in wanneer het begint.',
            'ends_on.required' => 'Vul in wanneer het eindigt.',
            'ends_on.after_or_equal' => 'De einddatum kan niet vóór de startdatum liggen.',
            'min_participants.lte' => 'Het minimum kan niet hoger zijn dan het aantal plekken.',
            'max_age.gte' => 'De maximumleeftijd kan niet lager zijn dan de minimumleeftijd.',
            'ends_at.after' => 'De eindtijd moet na de begintijd liggen.',
            'payment_options.*.amount.required' => 'Vul een bedrag in.',
            'payment_options.*.installments.between' => 'Kies tussen 2 en 12 termijnen.',
        ], [
            'name' => 'De naam',
            'description' => 'De omschrijving',
            'type' => 'Het soort aanbod',
            'billing_type' => 'De betaalwijze',
            'amount' => 'Het bedrag',
            'vat_rate' => 'Het btw-tarief',
            'credits' => 'Het aantal beurten',
            'validity_months' => 'De geldigheid',
            'interval' => 'De frequentie',
            'starts_on' => 'De startdatum',
            'ends_on' => 'De einddatum',
            'capacity' => 'Het aantal plekken',
            'min_participants' => 'Het minimum aantal deelnemers',
            'min_age' => 'De minimumleeftijd',
            'max_age' => 'De maximumleeftijd',
            'audience' => 'Voor wie',
            'sessions_count' => 'Het aantal sessies',
            'location_id' => 'De locatie',
            'status' => 'De status',
            'is_active' => 'De zichtbaarheid',
        ]);

        // Het bedrag komt als tekst binnen; pas na omzetting naar centen weet
        // je of het klopt. Nul mag: een proefles is gratis.
        $validator->after(function ($validator) use ($request) {
            if (Money::toCents((string) $request->input('amount')) < 0) {
                $validator->errors()->add('amount', 'Vul een bedrag van nul of hoger in, bijvoorbeeld 12,50.');
            }
        });

        $validated = $validator->validate();

        $periode = $type?->hasPeriod() ?? false;
        $capaciteit = $type?->hasCapacity() ?? false;

        $velden = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'billing_type' => $validated['billing_type'],
            'amount_cents' => Money::toCents($validated['amount']),
            'vat_rate' => $validated['vat_rate'],
            // Wat niet bij dit soort hoort wordt leeggemaakt.
            'credits' => $type?->needsCredits() ? $validated['credits'] : null,
            'validity_months' => $validated['validity_months'] ?? null,
            'interval' => $betaling?->isRecurring() ? $validated['interval'] : null,
            'starts_on' => $periode ? $validated['starts_on'] : null,
            'ends_on' => $periode ? $validated['ends_on'] : null,
            'capacity' => $capaciteit ? ($validated['capacity'] ?? null) : null,
            'min_participants' => $capaciteit ? ($validated['min_participants'] ?? null) : null,
            'min_age' => $validated['min_age'] ?? null,
            'max_age' => $validated['max_age'] ?? null,
            'audience' => $validated['audience'],
            'sessions_count' => $validated['sessions_count'] ?? null,
            // De gekozen locatie vult de tekst; die blijft staan zoals hij op
            // dat moment heette. Zie Location.
            'location_id' => $validated['location_id'] ?? null,
            'location' => isset($validated['location_id'])
                ? Location::whereKey($validated['location_id'])->value('name')
                : null,
            'status' => $validated['status'],
            'stops_at_end' => $validated['stops_at_end'],
            'is_active' => $validated['is_active'],
        ];

        // De standaard betaalvorm komt uit het prijsblok; daarachter de extra's.
        $betaalvormen = [[
            'type' => ($betaling?->isRecurring() ?? false) ? PaymentOptionType::Abonnement->value : PaymentOptionType::Eenmalig->value,
            'amount_cents' => Money::toCents($validated['amount']),
            'interval' => ($betaling?->isRecurring() ?? false) ? $validated['interval'] : null,
        ]];

        foreach ($validated['payment_options'] ?? [] as $optie) {
            $betaalvormen[] = [
                'type' => $optie['type'],
                'amount_cents' => max(0, Money::toCents($optie['amount'])),
                'installments' => $optie['installments'] ?? null,
                'interval' => $optie['interval'] ?? null,
                'label' => $optie['label'] ?? null,
            ];
        }

        $extra = [
            'payment_options' => $betaalvormen,
            'trainers' => $validated['trainers'] ?? [],
            'weekdays' => $validated['weekdays'] ?? [],
            'dates' => $validated['dates'] ?? [],
            'starts_at' => $validated['starts_at'] ?? '18:00',
            'ends_at' => $validated['ends_at'] ?? '19:30',
        ];

        return [$velden, $extra];
    }

    /** @return array<string, mixed> */
    protected function formData(?Product $product): array
    {
        return [
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'type' => $product->type->value,
                'billing_type' => $product->billing_type->value,
                'amount' => number_format($product->amount_cents / 100, 2, ',', ''),
                'vat_rate' => $product->vat_rate,
                'credits' => $product->credits,
                'validity_months' => $product->validity_months,
                'interval' => $product->interval?->value,
                'starts_on' => $product->starts_on?->format('Y-m-d'),
                'ends_on' => $product->ends_on?->format('Y-m-d'),
                'capacity' => $product->capacity,
                'min_participants' => $product->min_participants,
                'min_age' => $product->min_age,
                'max_age' => $product->max_age,
                'audience' => $product->audience->value,
                'sessions_count' => $product->sessions_count,
                // Alleen de extra's; de standaard staat in het prijsblok.
                'payment_options' => $product->paymentOptions()->where('is_default', false)->get()
                    ->map(fn (PaymentOption $optie) => [
                        'type' => $optie->type->value,
                        'amount' => number_format($optie->amount_cents / 100, 2, ',', ''),
                        'installments' => $optie->installments,
                        'interval' => $optie->interval,
                        'label' => $optie->label,
                    ])->values()->all(),
                'location' => $product->location,
                'location_id' => $product->location_id,
                'status' => $product->status->value,
                'stops_at_end' => $product->stops_at_end,
                'is_active' => $product->is_active,
                'trainers' => $product->trainers->pluck('id')->all(),
                'group_id' => $product->group?->id,
                'trainings_count' => $product->group?->trainings()->count() ?? 0,
            ] : null,
            'types' => $this->typen(),
            'intervals' => BillingInterval::options(),
            'billingTypes' => BillingType::options(),
            'audiences' => ProductAudience::options(),
            'paymentOptionTypes' => PaymentOptionType::options(),
            'statuses' => OfferingStatus::options(),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Location $locatie) => ['id' => $locatie->id, 'name' => $locatie->name]),
            // Trainers en de eigenaar: bij kleine scholen geeft die zelf ook les.
            'availableTrainers' => User::ofCurrentSchool()
                ->role([Role::Trainer->value, Role::Eigenaar->value])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
        ];
    }
}
