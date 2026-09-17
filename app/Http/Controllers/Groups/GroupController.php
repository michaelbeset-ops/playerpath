<?php

namespace App\Http\Controllers\Groups;

use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\GroupRequest;
use App\Models\Group;
use App\Models\Player;
use App\Models\Training;
use App\Support\Features\Features;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Groepen: waar je op plant, afvinkt en beoordeelt.
 *
 * Een groep is de knoop tussen spelers en trainingen. De detailpagina is de
 * plek waar je spelers erin zet en eruit haalt - met zoeken en meerdere
 * tegelijk, want een school die overstapt zet er twintig in één keer in. Vanaf
 * de speler kan het ook (zijn bewerkscherm); allebei schrijven ze dezelfde
 * koppeltabel, dus er is geen tweede waarheid.
 */
class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Group::class);

        $groups = Group::query()
            ->visibleTo($request->user())
            ->withCount([
                'players',
                'trainings as upcoming_trainings_count' => fn ($q) => $q->where('starts_at', '>=', now())->whereNull('cancelled_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'age_category' => $group->age_category,
                'is_active' => $group->is_active,
                'is_demo' => $group->is_demo,
                'players_count' => $group->players_count,
                'upcoming_trainings_count' => $group->upcoming_trainings_count,
            ]);

        return Inertia::render('groups/Index', [
            'groups' => $groups,
            'canManage' => $request->user()->can('create', Group::class),
        ]);
    }

    public function show(Request $request, Group $group): Response
    {
        $this->authorize('view', $group);

        $group->load(['product:id,name']);

        $spelers = $group->players()
            ->orderBy('first_name')->orderBy('last_name')
            ->get()
            ->map(fn (Player $speler) => [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'position' => $speler->position->label(),
                'age' => $speler->age,
                'photo' => $speler->photo_url,
                'is_active' => $speler->is_active,
            ]);

        $komend = $group->trainings()
            ->where('starts_at', '>=', now())
            ->whereNull('cancelled_at')
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (Training $t) => [
                'id' => $t->id,
                'date' => $t->starts_at->translatedFormat('D j M'),
                'time' => $t->starts_at->format('H:i'),
                'location' => $t->location,
            ]);

        $mag = $request->user()->can('update', $group);

        return Inertia::render('groups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'age_category' => $group->age_category,
                'is_active' => $group->is_active,
                'is_demo' => $group->is_demo,
                'product' => $group->product ? ['id' => $group->product->id, 'name' => $group->product->name] : null,
                'trainings_total' => $group->trainings()->count(),
            ],
            'players' => $spelers,
            'upcoming' => $komend,
            // Wie er nog bij kan: alle actieve spelers van de school die er nog
            // niet in zitten. Zoeken gebeurt in het scherm; een school heeft er
            // hooguit een paar honderd.
            'available' => $mag
                ? Player::query()->active()
                    ->whereNotIn('id', $group->players()->pluck('players.id'))
                    ->orderBy('first_name')->orderBy('last_name')
                    ->get()
                    ->map(fn (Player $speler) => [
                        'id' => $speler->id,
                        'name' => $speler->full_name,
                        'position' => $speler->position->label(),
                        'age' => $speler->age,
                        'age_category' => $speler->age_category,
                    ])
                : [],
            'can' => [
                'manage' => $mag,
                // Het aanbod is van de eigenaar, en bestaat alleen met Betalingen aan.
                'editProduct' => $request->user()->isEigenaar() && app(Features::class)->enabled(Feature::Betalingen),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Group::class);

        return Inertia::render('groups/Form', ['group' => null]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $group = Group::create($request->validated());

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'De groep is aangemaakt. Zet er nu spelers in.');
    }

    public function edit(Group $group): Response
    {
        $this->authorize('update', $group);

        return Inertia::render('groups/Form', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'age_category' => $group->age_category,
                'is_active' => $group->is_active,
                'is_demo' => $group->is_demo,
            ],
            // Verwijderen kan alleen bij een groep zonder trainingen en zonder
            // aanbod; het formulier zegt vooraf waarom het niet kan.
            'deleteBlocker' => $this->verwijderBlokkade($group),
        ]);
    }

    /**
     * Waarom deze groep niet verwijderd mag worden, of null als het wel kan.
     *
     * Trainingen en mededelingen hangen met een cascade aan de groep: de groep
     * weggooien zou het rooster, de aanwezigheid en verstuurde berichten
     * meenemen. Een groep van een aanbod is de knoop met de inschrijvingen.
     */
    protected function verwijderBlokkade(Group $group): ?string
    {
        if ($group->product_id !== null) {
            return 'Deze groep hoort bij een aanbod en kan daarom niet verwijderd worden. Zet de groep op niet actief.';
        }

        if ($group->trainings()->exists()) {
            return 'Deze groep heeft trainingen, met aanwezigheid en berichten eraan. Verwijderen zou die geschiedenis wissen; zet de groep daarom op niet actief.';
        }

        return null;
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'De groep is opgeslagen.');
    }

    /**
     * Meerdere spelers tegelijk in de groep. Wie er al in zit blijft er één
     * keer in staan; syncWithoutDetaching maakt geen dubbele koppelingen.
     */
    public function attachPlayers(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validate([
            'players' => ['required', 'array', 'min:1', 'max:200'],
            'players.*' => ['integer', Rule::exists('players', 'id')->where('school_id', app(Tenancy::class)->id())],
        ], [
            'players.required' => 'Kies minstens één speler.',
            'players.min' => 'Kies minstens één speler.',
        ]);

        $ids = array_values(array_unique($validated['players']));
        $group->players()->syncWithoutDetaching($ids);

        $aantal = count($ids);

        return back()->with('status', $aantal === 1
            ? 'De speler staat in de groep.'
            : "{$aantal} spelers staan in de groep.");
    }

    public function detachPlayer(Group $group, Player $player): RedirectResponse
    {
        $this->authorize('update', $group);

        $group->players()->detach($player->id);

        return back()->with('status', "{$player->first_name} is uit de groep gehaald. Zijn rapporten en aanwezigheid blijven staan.");
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        if ($reden = $this->verwijderBlokkade($group)) {
            return back()->withErrors(['group' => $reden]);
        }

        // De spelers zelf blijven bestaan; alleen hun indeling in deze groep
        // verdwijnt. Een groep opheffen mag nooit een speler wissen.
        $group->players()->detach();
        $group->delete();

        return redirect()
            ->route('groups.index')
            ->with('status', 'De groep is verwijderd. De spelers zijn bewaard gebleven.');
    }
}
