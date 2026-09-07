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
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het openbare inschrijfformulier van een school: /inschrijven/{slug}.
 *
 * Geen inlog. De school komt uit de slug in de URL — dat is hier wél de
 * bron, want er is geen ingelogde gebruiker. Alles wat het formulier
 * oplevert is een inschrijving die de eigenaar nog moet goedkeuren; er komt
 * dus nooit ongevraagd iemand in het ledenbestand.
 */
class PublicEnrollmentController extends Controller
{
    public function __construct(
        protected Tenancy $tenancy,
        protected Features $features,
        protected PaymentGateway $gateway,
    ) {}

    public function show(School $school): Response
    {
        abort_unless($school->is_active, 404);

        $tarieven = $this->tenancy->forSchool($school, fn () => Product::where('is_active', true)
            ->orderBy('amount_cents')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'amount' => Money::format($product->amount_cents),
                'type' => $product->type->label(),
                'is_subscription' => $product->type->isSubscription(),
                // Een kamp heeft geen frequentie; daar hoort "eenmalig" te staan
                // en niet de frequentie van een abonnement.
                'interval' => $product->type->isSubscription() ? $product->interval->label() : 'eenmalig',
            ]));

        return Inertia::render('enrollments/Public', [
            'school' => ['name' => $school->name, 'slug' => $school->slug],
            'products' => $tarieven,
            'positions' => PlayerPosition::options(),
            'paymentOptions' => $this->betaalopties($school),
            'submitted' => (bool) session('enrollment_submitted'),
        ]);
    }

    /**
     * Hoe wil je betalen?
     *
     * Twee gewone keuzes — contant bij de school of online — en voor een
     * abonnement daarnaast automatische incasso. Wat er niet kan wordt hier
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
            'product_id' => 'Het tarief',
            'payment_method' => 'De betaalmethode',
            'note' => 'De opmerking',
        ]);

        unset($validated['privacy']);

        $enrollment = $this->tenancy->forSchool($school, fn () => Enrollment::create($validated));

        // De eigenaar hoort het meteen, in de app en per mail.
        $eigenaren = User::where('school_id', $school->id)->role(Role::Eigenaar->value)->get();
        Notification::send($eigenaren, new NieuweInschrijving($enrollment));

        return redirect()
            ->route('enroll.show', $school)
            ->with('enrollment_submitted', true);
    }
}
