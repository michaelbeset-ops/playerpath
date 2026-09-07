<?php

namespace App\Http\Controllers\Enrollments;

use App\Enums\Feature;
use App\Enums\PaymentMethod;
use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Notifications\NieuweInschrijving;
use App\Support\Branding\Branding;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De openbare aanmeldpagina van een school: /inschrijven/{slug}.
 *
 * Geen inlog. De school komt uit de slug in de URL — dat is hier wél de bron,
 * want er is geen ingelogde gebruiker. Alles wat het formulier oplevert is een
 * inschrijving die de eigenaar nog moet goedkeuren; er komt dus nooit
 * ongevraagd iemand in het ledenbestand, en pas bij die goedkeuring ontstaan de
 * speler, het ouderaccount en de rekening.
 *
 * De pagina toont het **aanbod**: wat het is, wanneer het is, waar, voor welke
 * leeftijd, wat het kost en of er nog plek is. Een ouder kiest daaruit en vult
 * daarna pas gegevens in — andersom vragen we naam en geboortedatum van een
 * kind voordat iemand weet of er überhaupt iets bij zit.
 *
 * Wat er níét op staat: aanbod dat gesloten is, vol zit of niet zichtbaar
 * gezet is. Iets tonen waar je je niet op kunt aanmelden is een dode klik.
 */
class PublicEnrollmentController extends Controller
{
    public function __construct(
        protected Tenancy $tenancy,
        protected Features $features,
        protected PaymentGateway $gateway,
        protected Branding $branding,
    ) {}

    public function show(Request $request, School $school): Response
    {
        abort_unless($school->is_active, 404);

        $aanbod = $this->tenancy->forSchool($school, fn () => Product::query()
            ->where('is_active', true)
            ->withCount(['participations' => fn ($q) => $q->confirmed()])
            ->orderByRaw('starts_on is null')
            ->orderBy('starts_on')
            ->orderBy('amount_cents')
            ->get()
            // Vol aanbod blijft staan, met een wachtlijst erbij: "kom over drie
            // maanden nog eens kijken" is hoe je een gezin kwijtraakt. Gesloten
            // en onzichtbaar aanbod verdwijnt wel — daar valt niets te wachten.
            ->filter(fn (Product $product) => $product->is_active && $product->status->acceptsSignups())
            ->map(fn (Product $product) => $this->kaart($product))
            ->values());

        return Inertia::render('enrollments/Public', [
            'school' => ['name' => $school->name, 'slug' => $school->slug],
            'products' => $aanbod,
            // Waar een link vanaf de eigen website van de school op uitkomt.
            'selected' => $request->integer('aanbod') ?: null,
            'positions' => PlayerPosition::options(),
            'paymentOptions' => $this->betaalopties($school),
            'submitted' => (bool) session('enrollment_submitted'),
            // Vol: dan is het een plek op de wachtlijst geworden, en dat hoort
            // de bevestiging te zeggen in plaats van "tot snel".
            'onWaitlist' => session('enrollment_submitted') === 'waitlist',
        ]);
    }

    /**
     * Dezelfde pagina, maar dan op het subdomein van de school.
     *
     * Zonder APP_DOMAIN of zonder herkenbaar subdomein bestaat dit adres niet;
     * raden welke school bedoeld wordt levert alleen de verkeerde op.
     */
    public function onSubdomain(Request $request): Response
    {
        $school = $this->branding->fromHost($request->getHost());

        abort_if($school === null, 404);

        return $this->show($request, $school);
    }

    /**
     * Eén aanbod zoals een ouder het leest.
     *
     * @return array<string, mixed>
     */
    protected function kaart(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'type' => $product->type->label(),
            'amount' => Money::format($product->amount_cents),
            'is_free' => $product->amount_cents === 0,
            'billing' => $product->billing_type->short(),
            'is_subscription' => $product->isRecurring(),
            'interval' => $product->isRecurring() ? ($product->interval?->label() ?? 'per maand') : 'eenmalig',
            'starts_on' => $product->starts_on?->translatedFormat('j F Y'),
            'ends_on' => $product->ends_on?->translatedFormat('j F Y'),
            'location' => $product->location,
            'min_age' => $product->min_age,
            'max_age' => $product->max_age,
            'credits' => $product->type->needsCredits() ? $product->credits : null,
            // Hoeveel plekken er nog zijn. Null betekent: geen grens, en dan
            // hoort er ook niets te staan.
            'spots_left' => $product->spotsLeft(),
            'is_full' => $product->isFull(),
        ];
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        abort_unless($school->is_active, 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:'.now()->subYears(30)->toDateString()],
            'position' => ['required', Rule::enum(PlayerPosition::class)],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_email' => ['required', 'email', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('school_id', $school->id)->where('is_active', true)],
            // Alleen wat deze school op dit moment echt kan. Een verzoek met
            // 'ideal' terwijl er geen provider hangt hoort te stranden, niet
            // stilzwijgend te worden opgeslagen als een wens die nooit uitkomt.
            'payment_method' => ['nullable', Rule::in(array_column($this->betaalopties($school), 'value'))],
            'note' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['accepted'],
        ], [
            'privacy.accepted' => 'Je moet akkoord gaan met het gebruik van de gegevens.',
            'date_of_birth.before' => 'De geboortedatum moet in het verleden liggen.',
        ], [
            'first_name' => 'De voornaam',
            'last_name' => 'De achternaam',
            'date_of_birth' => 'De geboortedatum',
            'position' => 'De positie',
            'guardian_name' => 'Je naam',
            'guardian_email' => 'Je e-mailadres',
            'guardian_phone' => 'Je telefoonnummer',
            'relationship' => 'De relatie',
            'product_id' => 'Het aanbod',
            'payment_method' => 'De betaalmethode',
            'note' => 'De opmerking',
        ]);

        unset($validated['privacy']);

        // Twee dingen die pas hier te controleren zijn: is er nog plek, en past
        // de leeftijd? Allebei server-side, want een pagina die iemand in een
        // tabblad open had staan weet niet dat het blok inmiddels vol zit.
        $gekozenId = $validated['product_id'] ?? null;

        // Let op: de hele controle staat binnen forSchool(). Tellen hoeveel
        // plekken er bezet zijn is een query, en die valt buiten de scope
        // fail-closed op nul terug — dan zou een vol blok altijd nog plek
        // lijken te hebben.
        $fout = $gekozenId === null ? null : $this->tenancy->forSchool($school, function () use ($gekozenId, $validated) {
            $aanbod = Product::find($gekozenId);

            if ($aanbod === null) {
                return null;
            }

            if (! $aanbod->is_active || ! $aanbod->status->acceptsSignups()) {
                return ['product_id' => 'Voor dit aanbod kun je je op dit moment niet meer aanmelden.'];
            }

            if (! $aanbod->fitsAge(CarbonImmutable::parse($validated['date_of_birth'])->age)) {
                return ['date_of_birth' => $this->leeftijdsfout($aanbod)];
            }

            return null;
        });

        if ($fout !== null) {
            return back()->withErrors($fout)->withInput();
        }

        // Vol betekent niet "kom maar niet", maar wachten. Of dat zo is bepaalt
        // de server: iemand met de pagina in een tabblad weet niet dat de
        // laatste plek net weg is.
        $wachtlijst = $gekozenId !== null && $this->tenancy->forSchool(
            $school,
            fn () => Product::find($gekozenId)?->isFull() ?? false,
        );

        $validated['waitlist'] = $wachtlijst;

        $enrollment = $this->tenancy->forSchool($school, fn () => Enrollment::create($validated));

        // De eigenaar hoort het meteen, in de app en per mail.
        $eigenaren = User::where('school_id', $school->id)->role(Role::Eigenaar->value)->get();
        Notification::send($eigenaren, new NieuweInschrijving($enrollment));

        return redirect()
            ->route('enroll.show', $school)
            ->with('enrollment_submitted', $wachtlijst ? 'waitlist' : true);
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

    /**
     * Hoe wil je betalen?
     *
     * Twee gewone keuzes — contant bij de school of online — en voor iets dat
     * per maand loopt daarnaast automatische incasso. Wat er niet kan wordt hier
     * weggelaten in plaats van uitgegrijsd: een knop die niets doet laat je
     * zoeken naar wat je verkeerd deed.
     *
     * Online staat er alleen als er echt een provider hangt. Zonder Mollie is
     * "online betalen" een belofte die niemand kan inlossen; dan blijft alleen
     * contant over, en dat is precies hoe zo'n school het vandaag ook doet.
     *
     * @return list<array{value: string, label: string, hint: string, subscription_only: bool}>
     */
    protected function betaalopties(School $school): array
    {
        if (! $this->features->enabled(Feature::Betalingen, $school)) {
            return [];
        }

        $opties = [[
            'value' => PaymentMethod::Cash->value,
            'label' => 'Contant bij de school',
            'hint' => 'Je rekent af bij de school zelf.',
            'subscription_only' => false,
        ]];

        if (! $this->gateway->isConnected()) {
            return $opties;
        }

        $opties[] = [
            'value' => PaymentMethod::Ideal->value,
            'label' => 'Online met iDEAL',
            'hint' => 'Zodra de school je inschrijving goedkeurt, krijg je een betaallink per e-mail.',
            'subscription_only' => false,
        ];

        $opties[] = [
            'value' => PaymentMethod::DirectDebit->value,
            'label' => 'Automatische incasso',
            'hint' => 'De eerste betaling doe je zelf; daarna wordt het bedrag elke termijn afgeschreven.',
            'subscription_only' => true,
        ];

        return $opties;
    }
}
