<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\StartCheckout;
use App\Actions\Products\SellProduct;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
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
        protected PaymentGateway $gateway,
    ) {}

    public function index(Request $request): Response
    {
        $spelerIds = $this->eigenSpelers($request);

        $producten = Product::query()
            ->where('is_active', true)
            ->where('type', '!=', ProductType::Abonnement->value)
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
        ], [
            'player_id.in' => 'Kies een van je eigen spelers.',
        ], ['player_id' => 'De speler']);

        abort_unless($product->is_active, 404);

        // Een abonnement loopt door en heeft termijnen; dat regelt de school.
        abort_if($product->type->isSubscription(), 422, 'Een abonnement regel je via de school.');

        $speler = Player::findOrFail($validated['player_id']);

        $aankoop = $this->verkoop->handle($speler, $product);

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
        $ids = $request->user()->visiblePlayerIds();

        abort_if($ids === [], 403, 'Je hebt geen spelers om iets voor af te nemen.');

        return $ids;
    }
}
