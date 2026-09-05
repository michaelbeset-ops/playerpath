<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingInterval;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De abonnementsvormen van de school: wat kost wat, en hoe vaak.
 *
 * Dit kun je volledig inrichten zonder betaalprovider. Dat is precies de
 * bedoeling: eerst je tarieven op orde, dan pas het geld laten lopen.
 */
class PlanController extends Controller
{
    public function __construct(protected PaymentGateway $gateway) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Plan::class);

        $plans = Plan::query()
            ->withCount(['subscriptions' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'amount' => Money::format($plan->amount_cents),
                'interval' => $plan->interval->label(),
                'is_active' => $plan->is_active,
                'subscriptions_count' => $plan->subscriptions_count,
            ]);

        return Inertia::render('billing/Plans', [
            'plans' => $plans,
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
                'message' => $this->gateway->statusMessage(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Plan::class);

        return Inertia::render('billing/PlanForm', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Plan::class);

        $validated = $this->valideer($request);

        Plan::create($validated);

        return redirect()->route('plans.index')->with('status', 'De abonnementsvorm is aangemaakt.');
    }

    public function edit(Plan $plan): Response
    {
        $this->authorize('update', $plan);

        return Inertia::render('billing/PlanForm', $this->formData($plan));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $plan->update($this->valideer($request, $plan));

        return redirect()->route('plans.index')->with(
            'status',
            'De abonnementsvorm is opgeslagen. Lopende abonnementen houden hun oude bedrag.'
        );
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        // Lopende abonnementen blijven bestaan met hun eigen bedrag; alleen de
        // koppeling met deze vorm verdwijnt.
        $plan->subscriptions()->update(['plan_id' => null]);
        $plan->delete();

        return redirect()->route('plans.index')->with(
            'status',
            'De abonnementsvorm is verwijderd. Lopende abonnementen lopen gewoon door.'
        );
    }

    /** @return array<string, mixed> */
    protected function valideer(Request $request, ?Plan $plan = null): array
    {
        $validator = validator($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('plans', 'name')
                    ->where('school_id', app(Tenancy::class)->id())
                    ->ignore($plan?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            // Als tekst binnen, zodat "12,50" net zo goed werkt als "12.50".
            // Money::toCents doet de omzetting; nooit via een float.
            'amount' => ['required', 'string', 'max:20'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.unique' => 'Er bestaat al een abonnementsvorm met deze naam.',
        ], [
            'name' => 'De naam',
            'description' => 'De omschrijving',
            'amount' => 'Het bedrag',
            'interval' => 'De frequentie',
            'is_active' => 'De status',
        ]);

        // Het bedrag komt als tekst binnen; pas na omzetting naar centen weet
        // je of het klopt.
        $validator->after(function ($validator) use ($request) {
            if (Money::toCents((string) $request->input('amount')) <= 0) {
                $validator->errors()->add('amount', 'Vul een bedrag hoger dan nul in, bijvoorbeeld 12,50.');
            }
        });

        $validated = $validator->validate();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount_cents' => Money::toCents($validated['amount']),
            'interval' => $validated['interval'],
            'is_active' => $validated['is_active'],
        ];
    }

    /** @return array<string, mixed> */
    protected function formData(?Plan $plan): array
    {
        return [
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'amount' => number_format($plan->amount_cents / 100, 2, ',', ''),
                'interval' => $plan->interval->value,
                'is_active' => $plan->is_active,
            ] : null,
            'intervals' => BillingInterval::options(),
        ];
    }
}
