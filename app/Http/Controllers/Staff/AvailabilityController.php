<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Daypart;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Support\Availability\TrainerAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mijn beschikbaarheid.
 *
 * Een raster van zeven dagen bij drie dagdelen, plus een lijst uitzonderingen.
 * Bewust niet fijner: beschikbaarheid in kwartieren vragen is een agenda bouwen
 * die niemand invult, en een trainer weet wél of hij dinsdagavond kan.
 *
 * Het raster gaat in één keer op de bus (`PUT`), niet per vinkje: op een veld
 * met een slechte verbinding is één opslag die lukt of niet beter dan
 * eenentwintig die half aankomen.
 */
class AvailabilityController extends Controller
{
    public function __construct(protected TrainerAvailability $availability) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AvailabilityException::class);

        $user = $request->user();

        return Inertia::render('staff/Availability', [
            'grid' => $this->availability->grid($user),
            'exceptions' => $this->availability->exceptions($user),
            'hasSet' => $this->availability->hasSet($user),
            'dayparts' => array_map(fn (Daypart $deel) => [
                'value' => $deel->value,
                'label' => $deel->label(),
                'hint' => $deel->hint(),
            ], Daypart::cases()),
            // De eigenaar kan van hier naar het overzicht van zijn team.
            'canViewTeam' => $request->user()->can('viewTeam', AvailabilityException::class),
        ]);
    }

    /**
     * Het hele raster in één keer.
     *
     * Alles weggooien en opnieuw wegschrijven is hier eerlijker dan verschillen
     * bijhouden: een dagdeel dat er niet meer in staat is uitgevinkt, en dat is
     * precies wat het weghalen van de rij betekent.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('create', AvailabilityException::class);

        $data = $request->validate([
            'slots' => ['present', 'array'],
            'slots.*.weekday' => ['required', 'integer', 'between:1,7'],
            'slots.*.daypart' => ['required', Rule::in(Daypart::values())],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($data, $user) {
            AvailabilityRule::where('user_id', $user->id)->delete();

            // Dubbele vinkjes zijn geen tweede beschikbaarheid; die vangt de
            // unieke sleutel ook af, maar dan als foutmelding.
            $uniek = collect($data['slots'])
                ->unique(fn (array $rij) => $rij['weekday'].'-'.$rij['daypart']);

            foreach ($uniek as $rij) {
                AvailabilityRule::create([
                    'user_id' => $user->id,
                    'weekday' => (int) $rij['weekday'],
                    'daypart' => $rij['daypart'],
                ]);
            }
        });

        return back()->with('status', 'Je beschikbaarheid is opgeslagen.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $this->authorize('create', AvailabilityException::class);

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            // Een periode die eindigt voordat hij begint bestaat niet.
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'daypart' => ['nullable', Rule::in(Daypart::values())],
            'available' => ['boolean'],
            'note' => ['nullable', 'string', 'max:120'],
        ], [], [
            'starts_on' => 'begindatum',
            'ends_on' => 'einddatum',
            'note' => 'toelichting',
        ]);

        AvailabilityException::create([
            'user_id' => $request->user()->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'daypart' => $data['daypart'] ?? null,
            'available' => (bool) ($data['available'] ?? false),
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', 'De uitzondering staat erbij.');
    }

    public function destroyException(AvailabilityException $exception): RedirectResponse
    {
        $this->authorize('delete', $exception);

        $exception->delete();

        return back()->with('status', 'De uitzondering is weg.');
    }

    /**
     * Het overzicht van de eigenaar: waar kan wie.
     *
     * Staat naast de conflicten die op zijn dashboard verschijnen; dit is het
     * beeld waar hij zijn planning op maakt, dat zijn de gevallen waarin de
     * planning er al naast zit.
     */
    public function team(): Response
    {
        $this->authorize('viewTeam', AvailabilityException::class);

        return Inertia::render('staff/AvailabilityOverview', [
            'trainers' => $this->availability->overview(),
            'dayparts' => array_map(fn (Daypart $deel) => [
                'value' => $deel->value,
                'label' => $deel->label(),
                'hint' => $deel->hint(),
            ], Daypart::cases()),
            'conflicts' => $this->availability->conflicts(),
            'lookaheadDays' => TrainerAvailability::VOORUIT_DAGEN,
        ]);
    }
}
