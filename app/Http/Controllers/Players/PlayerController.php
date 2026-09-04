<?php

namespace App\Http\Controllers\Players;

use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Players\PlayerRequest;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
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
    public function __construct(protected CalculatePlayerCard $calculator) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'position' => (string) $request->string('position'),
            'group' => $request->integer('group') ?: null,
            'status' => (string) $request->string('status', 'active'),
        ];

        $players = Player::query()
            ->with('groups')
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $query->where(fn ($q) => $q
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->when($filters['position'] !== '', fn ($q) => $q->where('position', $filters['position']))
            ->when($filters['group'], fn ($q, $groupId) => $q->whereHas('groups', fn ($g) => $g->whereKey($groupId)))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->full_name,
                'position' => $player->position->label(),
                'position_value' => $player->position->value,
                'age' => $player->age,
                'is_active' => $player->is_active,
                'overall_rating' => $player->overall_rating,
                'groups' => $player->groups->pluck('name')->all(),
            ]);

        return Inertia::render('players/Index', [
            'players' => $players,
            'filters' => $filters,
            'positions' => PlayerPosition::options(),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('create', Player::class),
        ]);
    }

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
