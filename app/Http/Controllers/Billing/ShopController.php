<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Offerings\BookSlot;
use App\Actions\Offerings\JoinOffering;
use App\Actions\Payments\StartCheckout;
use App\Actions\Products\SellProduct;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Slot;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * De shop: wat een ouder zelf kan afnemen.
 *
 * Alles wat de school in haar prijslijst zet en géén abonnement is - een
 * rittenkaart van tien, een kamp, een clinic, kleding. De eigenaar vult dat
 * zelf in bij Producten; hier staat het te koop. Er is dus geen aparte lijst
 * die kan gaan afwijken van de prijslijst.
 *
 * **Abonnementen staan er bewust niet bij.** Die lopen door, en een ouder die
 * er zelf een aanzet naast het abonnement dat hij via de inschrijving al heeft,
 * krijgt twee keer per maand een rekening. Een abonnement regelt de school.
 *
 * Wie er koopt bepaalt visiblePlayerIds(): je koopt voor je eigen kind, en dat
 * is dezelfde bron als de trainingen en de spelerskaart.
 */
class ShopController extends Controller
{
    public function __construct(
        protected SellProduct $verkoop,
        protected StartCheckout $checkout,
        protected JoinOffering $deelname,
        protected BookSlot $boeking,
        protected PaymentGateway $gateway,
    ) {}

    public function index(Request $request): Response
    {
        $spelerIds = $this->eigenSpelers($request);

        $school = $request->user()->school;

        $producten = Product::query()
            ->where('is_active', true)
            ->withCount(['participations' => fn ($q) => $q->confirmed()])
            ->orderByRaw('starts_on is null')
            ->orderBy('starts_on')
            ->orderBy('amount_cents')
            ->get()
            // Alles waar je je op kunt aanmelden, ook doorlopende training: de
            // inschrijfstap regelt de betaalvorm. Gesloten aanbod niet.
            ->filter(fn (Product $product) => $product->status->acceptsSignups())
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'type' => $product->type->label(),
                'type_key' => $product->type->value,
                'amount' => Money::format($product->amount_cents),
                'is_free' => $product->amount_cents === 0,
                'billing' => $product->billing_type->short(),
                'period' => $this->periode($product),
                'location' => $product->location,
                'spots_left' => $product->spotsLeft(),
                'is_full' => $product->isFull(),
                'image' => $product->image_url,
                // Direct de inschrijving van dít aanbod in; voor wie kies je daar.
                'enroll_url' => route('enroll.show', $school).'?aanbod='.$product->id,
                // Wat je krijgt: beurten en hoe lang het geldig blijft.
                'credits' => $product->type->needsCredits() ? $product->credits : null,
                'validity_months' => $product->validity_months,
                // Bij een privétraining kies je een moment in plaats van je in
                // te schrijven op een vaste reeks.
                'slots' => $product->type === ProductType::Privetraining
                    ? $product->slots()->bookable()->with('trainer')->limit(20)->get()->map(fn (Slot $slot) => [
                        'id' => $slot->id,
                        'day' => $slot->starts_at->translatedFormat('l j F'),
                        'time' => $slot->starts_at->format('H:i').' – '.$slot->ends_at->format('H:i'),
                        'trainer' => $slot->trainer?->name,
                        'location' => $slot->location,
                    ])->values()
                    : [],
            ]);

        // Per soort gegroepeerd, in een vaste volgorde. Lege groepen laat het
        // scherm weg.
        $volgorde = [
            ProductType::Doorlopend, ProductType::Blok, ProductType::Kamp, ProductType::Privetraining,
            ProductType::SmallGroup, ProductType::LosseTraining, ProductType::Rittenkaart, ProductType::Proefles, ProductType::Overig,
        ];

        $groepen = collect($volgorde)->map(fn (ProductType $type) => [
            'key' => $type->value,
            'title' => $type->label(),
            'products' => $producten->where('type_key', $type->value)->values()->all(),
        ])->values();

        return Inertia::render('billing/Shop', [
            'groups' => $groepen,
            'players' => Player::whereIn('id', $spelerIds)
                ->orderBy('first_name')
                ->get()
                ->map(fn (Player $speler) => ['id' => $speler->id, 'name' => $speler->full_name]),
            'connected' => $this->gateway->isConnected(),
        ]);
    }

    public function store(Request $request, Product $product): HttpResponse|RedirectResponse
    {
        $spelerIds = $this->eigenSpelers($request);

        $validated = $request->validate([
            'player_id' => ['required', 'integer', Rule::in($spelerIds)],
            // Bij een privétraining hoort er een moment bij; anders staat er een
            // afspraak zonder tijd, en dan belt er iemand.
            'slot_id' => [
                Rule::requiredIf(fn () => $product->type === ProductType::Privetraining),
                'nullable', 'integer',
                Rule::exists('slots', 'id')->where('product_id', $product->id),
            ],
        ], [
            'player_id.in' => 'Kies een van je eigen spelers.',
            'slot_id.required' => 'Kies een moment.',
        ], ['player_id' => 'De speler', 'slot_id' => 'Het moment']);

        abort_unless($product->is_active, 404);

        // Een blok of kamp gaat via de inschrijving, met de controles op plek,
        // status, leeftijd en positie. Hier alleen wat nog open staat en past.
        if ($product->type !== ProductType::Privetraining) {
            abort_unless($product->acceptsSignups(), 404);

            $kind = Player::findOrFail($validated['player_id']);

            if (! $product->fitsAge($kind->age) || ! $product->fitsPosition($kind->position)) {
                return back()->withErrors(['payment' => 'Dit aanbod past niet bij de leeftijd of positie van '.$kind->first_name.'.']);
            }
        }

        // Wat per maand loopt heeft termijnen; dat regelt de school.
        abort_if($product->isRecurring(), 422, 'Aanbod per maand regel je via de school.');

        $speler = Player::findOrFail($validated['player_id']);

        // Een privétraining loopt anders: daar hoort een moment bij, en dat
        // moment wordt meteen de training in de agenda.
        if ($product->type === ProductType::Privetraining) {
            return $this->boek($product, $speler, (int) $validated['slot_id']);
        }

        $aankoop = $this->verkoop->handle($speler, $product);

        // Meedoen is meer dan betalen: hier hangt de deelnemerslijst van het
        // aanbod aan, en bij een blok ook de groep met de trainingen.
        $this->deelname->handle($product, $speler, purchase: $aankoop);

        /** @var Payment|null $betaling */
        $betaling = $aankoop->payments()->first();

        // Gratis is geen rekening: een proefles hoeft nergens heen.
        if ($betaling === null) {
            return redirect()->route('billing.index')->with('status', "{$aankoop->name} staat klaar voor {$speler->first_name}.");
        }

        try {
            $checkout = $this->checkout->handle($betaling, route('billing.return', $betaling));
        } catch (Throwable $e) {
            report($e);

            // De aankoop staat er wel: die is echt gedaan. Alleen het afrekenen
            // lukte niet, en dat kan vanuit het eigen overzicht alsnog.
            return redirect()->route('billing.index')
                ->withErrors(['payment' => 'Het starten van de betaling is niet gelukt. Je kunt het hier opnieuw proberen.']);
        }

        // Geen provider: dan staat de rekening open en rekent de ouder bij de
        // school af. Doen alsof er betaald is gebeurt nooit.
        if ($checkout === null) {
            return redirect()->route('billing.index')->with(
                'status',
                "{$aankoop->name} staat klaar voor {$speler->first_name}. Je rekent af bij de school.",
            );
        }

        return Inertia::location($checkout);
    }

    protected function periode(Product $product): ?string
    {
        if ($product->starts_on === null) {
            return null;
        }

        $start = $product->starts_on->translatedFormat('j F');

        return $product->ends_on === null || $product->ends_on->isSameDay($product->starts_on)
            ? $start
            : $start.' t/m '.$product->ends_on->translatedFormat('j F');
    }

    /**
     * De spelers van deze gebruiker. Zonder eigen speler valt er niets te
     * kopen - en te kiezen ook niet.
     *
     * @return list<int>
     */
    protected function eigenSpelers(Request $request): array
    {
        // Kopen doet de ouder. Een kind met een eigen inlog ziet zijn kaart
        // en zijn voortgang, maar sluit geen kamp of rittenkaart af.
        abort_unless($request->user()->isOuder(), 403, 'Inschrijven en kopen doen je ouders.');

        $ids = $request->user()->visiblePlayerIds();

        abort_if($ids === [], 403, 'Je hebt geen spelers om iets voor af te nemen.');

        return $ids;
    }

    /**
     * Een privétraining boeken op een gekozen moment.
     *
     * Net als bij de rest: is er een provider, dan reken je meteen af; anders
     * staat de rekening open en reken je bij de school af.
     */
    protected function boek(Product $product, Player $speler, int $slotId): HttpResponse|RedirectResponse
    {
        $slot = Slot::whereKey($slotId)->where('product_id', $product->id)->firstOrFail();

        try {
            $slot = $this->boeking->handle($slot, $speler);
        } catch (Throwable $e) {
            // Twee ouders die tegelijk op dezelfde knop drukken: dan is er één
            // te laat, en dat hoort er te staan in plaats van een lege pagina.
            return back()->withErrors(['slot_id' => $e->getMessage()]);
        }

        $betaling = $slot->purchase?->payments()->first();

        if ($betaling === null) {
            return redirect()->route('billing.index')->with(
                'status',
                "{$product->name} staat ingepland op {$slot->starts_at->translatedFormat('l j F')} om {$slot->starts_at->format('H:i')}.",
            );
        }

        try {
            $checkout = $this->checkout->handle($betaling, route('billing.return', $betaling));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('billing.index')
                ->withErrors(['payment' => 'Het starten van de betaling is niet gelukt. Je kunt het hier opnieuw proberen.']);
        }

        if ($checkout === null) {
            return redirect()->route('billing.index')->with(
                'status',
                "{$product->name} staat ingepland op {$slot->starts_at->translatedFormat('l j F')}. Je rekent af bij de school.",
            );
        }

        return Inertia::location($checkout);
    }
}
