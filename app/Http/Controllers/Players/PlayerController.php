<?php

namespace App\Http\Controllers\Players;

use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Players\PlayerRequest;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\CalculatePlayerCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het ledenbestand van de school.
 *
 * Alle queries lopen via de global scope, dus er staat hier nergens een
 * handmatige filter op school_id. Zie CLAUDE.md 3.1.
 */
class PlayerController extends Controller
{
    public function __construct(protected CalculatePlayerCard $calculator, protected GoalProgress $goals) {}

    // Het spelersoverzicht woont in Users\UserDirectoryController: spelers,
    // trainers en ouders staan daar samen onder Gebruikers.

    public function create(Request $request): Response
    {
        $this->authorize('create', Player::class);

        return Inertia::render('players/Form', $this->formData(null));
    }

    public function store(PlayerRequest $request): RedirectResponse
    {
        $player = Player::create($request->safe()->except('groups'));

        $player->groups()->sync($request->validated('groups') ?? []);

        return redirect()
            ->route('players.show', $player)
            ->with('status', 'De speler is toegevoegd.');
    }

    public function show(Request $request, Player $player): Response
    {
        // De detailpagina is een beheerscherm: hij toont onder meer de
        // contactgegevens van de ouders. Een ouder of speler hoort hier niet
        // te komen; die hebben de spelerskaart en de voortgangspagina.
        $this->authorize('viewAny', Player::class);
        $this->authorize('view', $player);

        $player->load(['groups', 'guardians']);

        return Inertia::render('players/Show', [
            'player' => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'name' => $player->full_name,
                'date_of_birth' => $player->date_of_birth->format('d-m-Y'),
                'age' => $player->age,
                'position' => $player->position->label(),
                'is_active' => $player->is_active,
                'overall_rating' => $player->overall_rating,
                'rated_at' => $player->rated_at?->format('d-m-Y'),
            ],
            'categories' => $this->calculator->breakdown($player),
            'groups' => $player->groups->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'age_category' => $group->age_category,
            ]),
            'guardians' => $player->guardians->map(fn ($guardian) => [
                'id' => $guardian->id,
                'name' => $guardian->name,
                'email' => $guardian->email,
                'relationship' => $guardian->pivot->relationship,
            ]),
            'reports' => $player->reports()
                ->newestFirst()
                ->with('trainer')
                ->limit(5)
                ->get()
                ->map(fn ($report) => [
                    'id' => $report->id,
                    'reported_on' => $report->reported_on->format('d-m-Y'),
                    'trainer' => $report->trainer?->name,
                    'note' => $report->note,
                ]),
            'reportCount' => $player->reports()->count(),
            'goals' => $this->goals->forPlayer($player),
            'goalCategories' => collect($player->position->categories())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
            // Ouders van deze school die nog niet aan deze speler hangen.
            'linkableGuardians' => User::ofCurrentSchool()
                ->role(Role::Ouder->value)
                ->whereNotIn('id', $player->guardians->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'can' => [
                'manage' => $request->user()->can('update', $player),
                'delete' => $request->user()->can('delete', $player),
                'report' => $request->user()->can('createReport', $player),
                'goals' => $request->user()->can('createFor', [App\Models\Goal::class, $player]),
            ],
        ]);
    }

    public function edit(Player $player): Response
    {
        $this->authorize('update', $player);

        return Inertia::render('players/Form', $this->formData($player));
    }

    public function update(PlayerRequest $request, Player $player): RedirectResponse
    {
        $player->update($request->safe()->except('groups'));

        $player->groups()->sync($request->validated('groups') ?? []);

        return redirect()
            ->route('players.show', $player)
            ->with('status', 'De gegevens van de speler zijn opgeslagen.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $this->authorize('delete', $player);

        $player->delete();

        return redirect()
            ->route('players.index')
            ->with('status', 'De speler en alle bijbehorende rapporten zijn verwijderd.');
    }

    /** @return array<string, mixed> */
    protected function formData(?Player $player): array
    {
        return [
            'player' => $player ? [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'date_of_birth' => $player->date_of_birth->format('Y-m-d'),
                'position' => $player->position->value,
                'is_active' => $player->is_active,
                'groups' => $player->groups->pluck('id')->all(),
            ] : null,
            'positions' => PlayerPosition::options(),
            'availableGroups' => Group::orderBy('name')->get(['id', 'name', 'age_category']),
        ];
    }
}
