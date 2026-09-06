<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingInterval;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wat de school verkoopt: abonnementen, rittenkaarten, losse trainingen, kampen.
 *
 * Dit kun je volledig inrichten zonder betaalprovider. Dat is precies de
 * bedoeling: eerst je aanbod op orde, dan pas het geld laten lopen.
 */
class ProductController extends Controller
{
    public function __construct(protected PaymentGateway $gateway) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $producten = Product::query()
            ->withCount([
                'subscriptions' => fn ($q) => $q->active(),
                'purchases' => fn ($q) => $q->active(),
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

        Product::create($this->valideer($request));

        return redirect()->route('products.index')->with('status', 'Het product is aangemaakt.');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('billing/ProductForm', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->update($this->valideer($request, $product));

        return redirect()->route('products.index')->with(
            'status',
            'Het product is opgeslagen. Wat er al is afgenomen houdt zijn oude bedrag.'
        );
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        // Lopende afspraken blijven bestaan met hun eigen bedrag; alleen de
        // verwijzing naar dit product verdwijnt.
        $product->subscriptions()->update(['product_id' => null]);
        $product->purchases()->update(['product_id' => null]);
        $product->delete();

        return redirect()->route('products.index')->with(
            'status',
            'Het product is verwijderd. Wat er al is afgenomen loopt gewoon door.'
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
            'amount' => Money::format($product->amount_cents),
            'amount_excl_vat' => Money::format($product->amountExclVatCents()),
            'vat_rate' => $product->vat_rate,
            'credits' => $product->credits,
            'validity_months' => $product->validity_months,
            'interval' => $product->interval?->label(),
            'is_active' => $product->is_active,
            'subscriptions_count' => $product->subscriptions_count ?? 0,
            'purchases_count' => $product->purchases_count ?? 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function typen(): array
    {
        return array_map(fn (ProductType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'needs_interval' => $type->needsInterval(),
            'needs_credits' => $type->needsCredits(),
        ], ProductType::cases());
    }

    /** @return array<string, mixed> */
    protected function valideer(Request $request, ?Product $product = null): array
    {
        $type = ProductType::tryFrom((string) $request->input('type'));

        $validator = validator($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'name')
                    ->where('school_id', app(Tenancy::class)->id())
                    ->ignore($product?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            // Als tekst binnen, zodat "12,50" net zo goed werkt als "12.50".
            // Money::toCents doet de omzetting; nooit via een float.
            'amount' => ['required', 'string', 'max:20'],
            // Btw-tarieven zoals ze in Nederland bestaan. Sportlessen vallen
            // vaak onder het lage tarief en soms onder een vrijstelling; dat
            // verschilt per school, dus het is een keuze en geen constante.
            'vat_rate' => ['required', 'integer', Rule::in([0, 9, 21])],
            'credits' => [Rule::requiredIf(fn () => $type?->needsCredits() ?? false), 'nullable', 'integer', 'between:1,500'],
            'validity_months' => ['nullable', 'integer', 'between:1,120'],
            'interval' => [Rule::requiredIf(fn () => $type?->needsInterval() ?? false), 'nullable', Rule::enum(BillingInterval::class)],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.unique' => 'Er bestaat al een product met deze naam.',
            'credits.required' => 'Vul in hoeveel beurten er op de kaart staan.',
            'interval.required' => 'Kies hoe vaak een abonnement in rekening wordt gebracht.',
        ], [
            'name' => 'De naam',
            'description' => 'De omschrijving',
            'type' => 'Het soort product',
            'amount' => 'Het bedrag',
            'vat_rate' => 'Het btw-tarief',
            'credits' => 'Het aantal beurten',
            'validity_months' => 'De geldigheid',
            'interval' => 'De frequentie',
            'is_active' => 'De status',
        ]);

        // Het bedrag komt als tekst binnen; pas na omzetting naar centen weet
        // je of het klopt. Nul mag: een proefles is gratis.
        $validator->after(function ($validator) use ($request) {
            if (Money::toCents((string) $request->input('amount')) < 0) {
                $validator->errors()->add('amount', 'Vul een bedrag van nul of hoger in, bijvoorbeeld 12,50.');
            }
        });

        $validated = $validator->validate();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'amount_cents' => Money::toCents($validated['amount']),
            'vat_rate' => $validated['vat_rate'],
            // Wat niet bij dit soort hoort wordt leeggemaakt: een rittenkaart
            // met een maandfrequentie is een veld dat later niemand meer snapt.
            'credits' => $type?->needsCredits() ? $validated['credits'] : null,
            'validity_months' => $validated['validity_months'] ?? null,
            'interval' => $type?->needsInterval() ? $validated['interval'] : null,
            'is_active' => $validated['is_active'],
        ];
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
                'amount' => number_format($product->amount_cents / 100, 2, ',', ''),
                'vat_rate' => $product->vat_rate,
                'credits' => $product->credits,
                'validity_months' => $product->validity_months,
                'interval' => $product->interval?->value,
                'is_active' => $product->is_active,
            ] : null,
            'types' => $this->typen(),
            'intervals' => BillingInterval::options(),
        ];
    }
}
