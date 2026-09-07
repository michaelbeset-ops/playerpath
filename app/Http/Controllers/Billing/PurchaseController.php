<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Products\SellProduct;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Product;
use App\Models\Purchase;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Een product aan een speler toekennen, of weer intrekken.
 *
 * Staat op de pagina van de speler, want daar zit je als een ouder aan de
 * telefoon vraagt om een rittenkaart.
 */
class PurchaseController extends Controller
{
    public function __construct(protected SellProduct $verkoop) {}

    public function store(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('update', $player);

        $validated = $request->validate([
            // Rule::exists gaat buiten de global scope om; daarom hier
            // expliciet de school erbij. Zie CLAUDE.md.
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')
                    ->where('school_id', app(Tenancy::class)->id())
                    ->where('is_active', true),
            ],
            'starts_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'product_id.required' => 'Kies een product.',
            'product_id.exists' => 'Dit product bestaat niet (meer).',
        ], [
            'product_id' => 'Het product',
            'starts_on' => 'De startdatum',
            'note' => 'De notitie',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // Wat per maand loopt gaat via het abonnementenscherm: daar horen
        // termijnen en incasso bij. Ze door elkaar halen levert dubbele
        // rekeningen op.
        abort_if($product->isRecurring(), 422, 'Aanbod per maand ken je toe via Abonnementen.');

        $aankoop = $this->verkoop->handle(
            $player,
            $product,
            isset($validated['starts_on']) ? Carbon::parse($validated['starts_on']) : null,
            $validated['note'] ?? null,
        );

        return back()->with('status', "{$aankoop->name} is toegevoegd aan {$player->first_name}.");
    }

    /**
     * Intrekken.
     *
     * Bewust geen verwijderen: wat er is afgenomen hoort in de historie te
     * blijven staan, en de rekening die eraan hangt ook. Alleen het saldo van
     * een rittenkaart telt niet meer mee.
     */
    public function destroy(Player $player, Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $player);

        abort_unless($purchase->player_id === $player->id, 404);

        $purchase->update(['status' => 'cancelled']);

        return back()->with('status', "{$purchase->name} is ingetrokken.");
    }
}
