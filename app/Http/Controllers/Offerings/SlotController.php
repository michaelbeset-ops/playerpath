<?php

namespace App\Http\Controllers\Offerings;

use App\Actions\Offerings\BookSlot;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De beschikbare momenten bij een privétraining.
 *
 * Een blok schrijf je je op in; een privétraining boek je. De school zet hier
 * neer wanneer welke trainer kan, en een ouder kiest daaruit in de shop.
 *
 * Momenten maak je per stuk of in een reeks — "elke dinsdag van 16:00 tot 17:00,
 * vier weken lang" is hoe een trainer zijn agenda beschrijft, niet als zestien
 * losse formulieren.
 */
class SlotController extends Controller
{
    public function index(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $momenten = $product->slots()
            ->with(['trainer', 'player'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Slot $slot) => [
                'id' => $slot->id,
                'day' => $slot->starts_at->format('Y-m-d'),
                'day_label' => $slot->starts_at->translatedFormat('l j F Y'),
                'time' => $slot->starts_at->format('H:i').' – '.$slot->ends_at->format('H:i'),
                'trainer' => $slot->trainer?->name,
                'location' => $slot->location,
                'player' => $slot->player?->full_name,
                'player_id' => $slot->player_id,
                // Vastgehouden door een inschrijving die nog op goedkeuring wacht.
                'reserved' => $slot->player_id === null && $slot->enrollment_id !== null,
                'has_passed' => $slot->starts_at->isPast(),
            ]);

        return Inertia::render('offerings/Slots', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->label(),
                'location' => $product->location,
            ],
            'slots' => $momenten,
            'locations' => Location::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Location $locatie) => ['id' => $locatie->id, 'name' => $locatie->name]),
            'trainers' => User::ofCurrentSchool()
                ->role([Role::Trainer->value, Role::Eigenaar->value])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
        ]);
    }

    /**
     * Momenten toevoegen: één dag, of wekelijks herhaald.
     *
     * Losse momenten, geen reeks-entiteit — net als bij het herhalen van
     * trainingen. Eén moment verzetten of weghalen raakt de rest niet.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $schoolId = $product->school_id;

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'location_id' => [
                'nullable', 'integer',
                Rule::exists('locations', 'id')->where('school_id', $schoolId),
            ],
            'repeat_weeks' => ['nullable', 'integer', 'between:1,26'],
        ], [
            'ends_at.after' => 'De eindtijd moet na de begintijd liggen.',
        ], [
            'date' => 'De datum',
            'starts_at' => 'De begintijd',
            'ends_at' => 'De eindtijd',
            'user_id' => 'De trainer',
            'location_id' => 'De locatie',
            'repeat_weeks' => 'Het aantal weken',
        ]);

        $weken = max(1, (int) ($validated['repeat_weeks'] ?? 1));
        $dag = CarbonImmutable::parse($validated['date']);
        $aantal = 0;

        for ($week = 0; $week < $weken; $week++) {
            $start = $dag->addWeeks($week)->setTimeFromTimeString($validated['starts_at']);
            $eind = $dag->addWeeks($week)->setTimeFromTimeString($validated['ends_at']);

            // Hetzelfde uur twee keer neerzetten levert een ouder een lijst met
            // dubbele momenten op, en dat is precies waar hij op afhaakt.
            $bestaat = $product->slots()
                ->where('starts_at', $start)
                ->where('user_id', $validated['user_id'] ?? null)
                ->exists();

            if ($bestaat) {
                continue;
            }

            $locatieId = $validated['location_id'] ?? $product->location_id;

            $product->slots()->create([
                'user_id' => $validated['user_id'] ?? null,
                'starts_at' => $start,
                'ends_at' => $eind,
                'location_id' => $locatieId,
                // De naam zoals hij nu heet; zie Location.
                'location' => $locatieId === null
                    ? $product->location
                    : Location::whereKey($locatieId)->value('name'),
            ]);

            $aantal++;
        }

        return back()->with('status', $aantal === 1 ? 'Het moment staat erbij.' : "Er staan {$aantal} momenten bij.");
    }

    /**
     * Een moment weghalen, of een boeking terugdraaien.
     *
     * Een geboekt moment weghalen is een boeking annuleren; de rekening blijft
     * staan. Wat er is afgesproken hoort in de historie te blijven — of er iets
     * terugbetaald wordt is een gesprek tussen school en ouder.
     */
    public function destroy(Request $request, Product $product, Slot $slot, BookSlot $boeken): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($slot->product_id === $product->id, 404);

        if ($slot->player_id !== null) {
            $boeken->release($slot);

            return back()->with('status', 'De boeking is teruggedraaid. Het moment is weer vrij.');
        }

        $slot->delete();

        return back()->with('status', 'Het moment is weggehaald.');
    }
}
