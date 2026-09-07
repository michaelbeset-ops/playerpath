<?php

namespace App\Http\Controllers\Schools;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De locaties van een school.
 *
 * Klein scherm met opzet: een school heeft er twee of drie. Wat het oplevert is
 * dat "Sportpark De Vliert" één ding is in plaats van een tekst die elke keer
 * net anders wordt ingetikt.
 *
 * Verwijderen bestaat niet — een locatie die je niet meer gebruikt zet je op
 * niet-actief. Wat er in de agenda van vorig seizoen staat hoort te blijven
 * kloppen.
 */
class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Location::class);

        $locaties = Location::query()
            ->withCount(['trainings', 'products', 'slots'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Location $locatie) => [
                'id' => $locatie->id,
                'name' => $locatie->name,
                'address' => $locatie->address,
                'note' => $locatie->note,
                'is_active' => $locatie->is_active,
                'trainings_count' => $locatie->trainings_count,
                'products_count' => $locatie->products_count,
                'slots_count' => $locatie->slots_count,
            ]);

        return Inertia::render('schools/Locations', [
            'locations' => $locaties,
            'canManage' => $request->user()->can('create', Location::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Location::class);

        Location::create($this->valideer($request));

        return back()->with('status', 'De locatie staat erbij.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        $location->update($this->valideer($request, $location));

        // De naam ergens anders bijwerken doen we bewust niet: wat er in de
        // agenda staat draagt de naam van toen. Zie Location.
        return back()->with('status', 'De locatie is opgeslagen.');
    }

    /** @return array<string, mixed> */
    protected function valideer(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('locations', 'name')
                    ->where('school_id', app(Tenancy::class)->id())
                    ->ignore($location?->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.unique' => 'Er bestaat al een locatie met deze naam.',
        ], [
            'name' => 'De naam',
            'address' => 'Het adres',
            'note' => 'De notitie',
            'is_active' => 'De status',
        ]);
    }
}
