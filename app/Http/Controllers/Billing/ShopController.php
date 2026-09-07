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
 * Alles wat de school in haar prijslijst zet en géén abonnement is — een
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

        $producten = Product::query()
            ->where('is_active', true)
            ->purchasable()
            ->orderBy('amount_cents')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'type' => $product->type->label(),
                'amount' => Money::format($product->amount_cents),
                'is_free' => $product->amount_cents === 0,
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

        return Inertia::render('billing/Shop', [
            'products' => $producten,
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

    /**
     * De spelers van deze gebruiker. Zonder eigen speler valt er niets te
     * kopen — en te kiezen ook niet.
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
