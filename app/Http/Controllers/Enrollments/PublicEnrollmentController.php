<?php

namespace App\Http\Controllers\Enrollments;

use App\Actions\Enrollments\SubmitEnrollment;
use App\Enums\PlayerPosition;
use App\Http\Controllers\Controller;
use App\Models\ConsentDocument;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Support\Branding\Branding;
use App\Support\Enrollment\EnrollmentForm;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\OrderBuilder;
use App\Support\Money\Money;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * De openbare inschrijfflow van een school: /inschrijven/{slug}.
 *
 * Geen inlog vooraf. De school komt uit de slug in de URL — dat is hier wél
 * de bron, want er is geen ingelogde gebruiker. Een ouder kiest een aanbod,
 * vult zijn kind(eren) in, maakt onderweg een account, geeft toestemmingen,
 * kiest hoe hij betaalt en ziet vóór het bevestigen precies wat hij betaalt
 * en waarvoor. Is hij al ingelogd, dan staan naam, e-mail en kinderen klaar.
 *
 * Wat hier gecontroleerd wordt en nergens anders: leeftijd en positie ten
 * opzichte van het aanbod, en of het vol zit. Allebei server-side, binnen de
 * school; een pagina die iemand in een tabblad had staan weet niet dat de
 * laatste plek net weg is.
 */
class PublicEnrollmentController extends Controller
{
    public function __construct(
        protected Tenancy $tenancy,
        protected Branding $branding,
        protected EnrollmentForm $form,
        protected OrderBuilder $orders,
        protected SubmitEnrollment $indienen,
    ) {}

    public function show(Request $request, School $school): Response
    {
        abort_unless($school->is_active, 404);

        $ouder = $this->ouder($request, $school);

        return $this->tenancy->forSchool($school, function () use ($request, $school, $ouder) {
            $instellingen = EnrollmentSettings::for($school);

            $aanbod = Product::query()
                ->where('is_active', true)
                ->with('paymentOptions')
                ->withCount(['participations' => fn ($q) => $q->confirmed()])
                ->orderByRaw("type = 'proefles' desc")
                ->orderByRaw('starts_on is null')
                ->orderBy('starts_on')
                ->orderBy('amount_cents')
                ->get()
                // Vol aanbod blijft staan (met wachtlijst, als de school die
                // aan heeft); gesloten en onzichtbaar aanbod verdwijnt.
                ->filter(fn (Product $p) => $p->status->acceptsSignups() && ($instellingen->hasWaitlist() || ! $p->isFull()))
                ->map(fn (Product $p) => $this->form->product($p))
                ->values();

            return Inertia::render('enrollments/Public', [
                'school' => ['name' => $school->name, 'slug' => $school->slug],
                'products' => $aanbod,
                'selected' => $request->integer('aanbod') ?: null,
                'config' => $this->form->for($school, $instellingen, $ouder),
                'loginUrl' => route('login'),
                'submitted' => session('enrollment_submitted'),
            ]);
        });
    }

    public function onSubdomain(Request $request): Response
    {
        $school = $this->branding->fromHost($request->getHost());

        abort_if($school === null, 404);

        return $this->show($request, $school);
    }

    /** Het overzicht: wat betaal je en waarvoor, nog vóór je bevestigt. */
    public function preview(Request $request, School $school): JsonResponse
    {
        abort_unless($school->is_active, 404);

        $ouder = $this->ouder($request, $school);

        return $this->tenancy->forSchool($school, function () use ($request, $school, $ouder) {
            $instellingen = EnrollmentSettings::for($school);
            $validated = $request->validate($this->kindRegels($school, alleenKeuze: true));

            $regels = $this->regels($validated['children'], $ouder);

            return response()->json($this->orders->build($instellingen, $regels, $ouder, $request->input('code')));
        });
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        abort_unless($school->is_active, 404);

        $ouder = $this->ouder($request, $school);

        return $this->tenancy->forSchool($school, function () use ($request, $school, $ouder) {
            $instellingen = EnrollmentSettings::for($school);
            $verplicht = collect(ConsentDocument::allForSchool())->where('required', true)->pluck('key')->all();
            $methoden = array_column($this->form->betaalmethoden($school), 'value');

            $validated = $request->validate([
                ...$this->kindRegels($school),
                'guardian_name' => [Rule::requiredIf($ouder === null), 'nullable', 'string', 'max:255'],
                'guardian_email' => [Rule::requiredIf($ouder === null), 'nullable', 'email', 'max:255'],
                'guardian_phone' => ['nullable', 'string', 'max:40'],
                'relationship' => ['nullable', 'string', 'max:50'],
                'password' => [Rule::requiredIf($ouder === null), 'nullable', 'string', 'min:8', 'max:200'],
                'consents' => ['nullable', 'array'],
                'consents.*' => ['string', Rule::in(array_keys(ConsentDocument::SOORTEN))],
                'payment_method' => [Rule::requiredIf($methoden !== []), 'nullable', Rule::in($methoden)],
                'code' => ['nullable', 'string', 'max:40'],
                'note' => ['nullable', 'string', 'max:2000'],
            ], [
                'password.min' => 'Kies een wachtwoord van minstens 8 tekens.',
                'date_of_birth.before' => 'De geboortedatum moet in het verleden liggen.',
            ], [
                'guardian_name' => 'Je naam',
                'guardian_email' => 'Je e-mailadres',
                'guardian_phone' => 'Je telefoonnummer',
                'password' => 'Het wachtwoord',
                'payment_method' => 'De betaalmethode',
                'code' => 'De kortingscode',
                'note' => 'De opmerking',
            ]);

            // Verplichte toestemmingen zijn verplicht, welke dat zijn zegt de school.
            $ontbreekt = array_diff($verplicht, $validated['consents'] ?? []);

            if ($ontbreekt !== []) {
                throw ValidationException::withMessages([
                    'consents' => 'Ga akkoord met '.collect(ConsentDocument::allForSchool())->whereIn('key', $ontbreekt)->pluck('title')->join(', ', ' en ').'.',
                ]);
            }

            // Leeftijd, positie en of het aanbod nog openstaat: hier, niet in het formulier.
            $this->regels($validated['children'], $ouder);

            try {
                $uitkomst = $this->indienen->handle(
                    $instellingen,
                    $ouder,
                    [
                        'name' => $validated['guardian_name'] ?? $ouder?->name,
                        'email' => $validated['guardian_email'] ?? $ouder?->email,
                        'phone' => $validated['guardian_phone'] ?? null,
                        'relationship' => $validated['relationship'] ?? null,
                        'password' => $validated['password'] ?? null,
                        'payment_method' => $validated['payment_method'] ?? null,
                    ],
                    $validated['children'],
                    $validated['consents'] ?? [],
                    $validated['code'] ?? null,
                    $validated['note'] ?? null,
                    $request->ip(),
                );
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['guardian_email' => $e->getMessage()]);
            }

            $eerste = $uitkomst['enrollments'][0];

            return redirect()
                ->route('enroll.show', $school)
                ->with('enrollment_submitted', [
                    'status' => $eerste->status->value,
                    'names' => collect($uitkomst['enrollments'])->pluck('first_name')->join(', ', ' en '),
                    'new_account' => $uitkomst['new_account'],
                    'total' => $uitkomst['order']?->total_cents ? Money::format($uitkomst['order']->total_cents) : null,
                    'offline' => ($validated['payment_method'] ?? null) === 'cash',
                ]);
        });
    }

    /**
     * De validatieregels voor de kinderen. Rule::exists gaat buiten de scope om,
     * dus de school staat er expliciet bij.
     *
     * @return array<string, mixed>
     */
    protected function kindRegels(School $school, bool $alleenKeuze = false): array
    {
        $instellingen = EnrollmentSettings::for($school);
        $veld = fn (string $naam) => match ($instellingen->field($naam)) {
            'required' => ['required', 'string', 'max:200'],
            'optional' => ['nullable', 'string', 'max:200'],
            default => ['prohibited'],
        };

        $regels = [
            'children' => ['required', 'array', 'min:1', 'max:6'],
            'children.*.player_id' => ['nullable', 'integer'],
            'children.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('school_id', $school->id)->where('is_active', true)],
            'children.*.payment_option_id' => ['required', 'integer', Rule::exists('payment_options', 'id')->where('school_id', $school->id)],
        ];

        if ($alleenKeuze) {
            $regels['children.*.first_name'] = ['nullable', 'string', 'max:255'];
            $regels['children.*.date_of_birth'] = ['nullable', 'date'];
            $regels['children.*.position'] = ['nullable', Rule::enum(PlayerPosition::class)];

            return $regels;
        }

        return [
            ...$regels,
            'children.*.first_name' => ['required_without:children.*.player_id', 'nullable', 'string', 'max:255'],
            'children.*.last_name' => ['required_without:children.*.player_id', 'nullable', 'string', 'max:255'],
            'children.*.date_of_birth' => ['required_without:children.*.player_id', 'nullable', 'date', 'before:today', 'after:'.now()->subYears(30)->toDateString()],
            'children.*.position' => [
                $instellingen->field('positie') === 'off' ? 'nullable' : 'required_without:children.*.player_id',
                'nullable', Rule::enum(PlayerPosition::class),
            ],
            'children.*.details' => ['nullable', 'array'],
            'children.*.details.kledingmaat' => $veld('kledingmaat'),
            'children.*.details.niveau' => $veld('niveau'),
            'children.*.details.medisch' => $instellingen->field('medisch') === 'off' ? ['prohibited'] : ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * De regels voor OrderBuilder, met de controles die alleen de server kan
     * doen: past de leeftijd, past de positie, staat het aanbod nog open.
     *
     * @param  list<array<string, mixed>>  $kinderen
     * @return list<array{product: Product, option: PaymentOption, child_name: string, player_id: int|null}>
     */
    protected function regels(array $kinderen, ?User $ouder): array
    {
        $regels = [];

        foreach ($kinderen as $i => $kind) {
            $aanbod = Product::with('paymentOptions')->findOrFail($kind['product_id']);
            $optie = $aanbod->paymentOptions->firstWhere('id', (int) $kind['payment_option_id']);

            if ($optie === null) {
                throw ValidationException::withMessages(["children.{$i}.payment_option_id" => 'Kies een betaalvorm van dit aanbod.']);
            }

            if (! $aanbod->status->acceptsSignups()) {
                throw ValidationException::withMessages(["children.{$i}.product_id" => 'Voor dit aanbod kun je je op dit moment niet meer aanmelden.']);
            }

            $bestaand = ! empty($kind['player_id']) && $ouder !== null ? $ouder->children()->find($kind['player_id']) : null;

            if (! empty($kind['player_id']) && $bestaand === null) {
                throw ValidationException::withMessages(["children.{$i}.player_id" => 'Dit kind hoort niet bij jouw account.']);
            }

            $geboren = $bestaand?->date_of_birth ?? (isset($kind['date_of_birth']) ? CarbonImmutable::parse($kind['date_of_birth']) : null);
            $positie = $bestaand?->position ?? (isset($kind['position']) ? PlayerPosition::from($kind['position']) : null);

            if ($geboren !== null && ! $aanbod->fitsAge($geboren->age)) {
                throw ValidationException::withMessages(["children.{$i}.date_of_birth" => $this->leeftijdsfout($aanbod)]);
            }

            if ($positie !== null && ! $aanbod->fitsPosition($positie)) {
                throw ValidationException::withMessages(["children.{$i}.position" => "Dit aanbod is voor {$aanbod->audience->label()}."]);
            }

            $regels[] = [
                'product' => $aanbod,
                'option' => $optie,
                'child_name' => $bestaand?->first_name ?? ($kind['first_name'] ?: 'je kind'),
                'player_id' => $bestaand?->id,
            ];
        }

        return $regels;
    }

    protected function leeftijdsfout(Product $aanbod): string
    {
        if ($aanbod->min_age !== null && $aanbod->max_age !== null) {
            return "Dit aanbod is voor kinderen van {$aanbod->min_age} tot en met {$aanbod->max_age} jaar.";
        }

        if ($aanbod->min_age !== null) {
            return "Dit aanbod is vanaf {$aanbod->min_age} jaar.";
        }

        return "Dit aanbod is tot en met {$aanbod->max_age} jaar.";
    }

    /** De ingelogde ouder van déze school; iemand van een andere school telt als bezoeker. */
    protected function ouder(Request $request, School $school): ?User
    {
        $user = $request->user();

        return $user !== null && $user->school_id === $school->id && $user->isOuder() ? $user : null;
    }
}
