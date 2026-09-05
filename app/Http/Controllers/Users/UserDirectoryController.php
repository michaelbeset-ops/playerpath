<?php

namespace App\Http\Controllers\Users;

use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gebruikers: spelers, trainers en ouders bij elkaar.
 *
 * Eén scherm met drie tabbladen in plaats van drie losse menu-items — je
 * zoekt zelden iets over "trainers" los van "wie zitten er op mijn school".
 *
 * Let op het verschil: een **speler** is een profiel (tabel players) met een
 * optioneel eigen inlogaccount; een **trainer** of **ouder** is altijd een
 * account (tabel users). Die twee dingen zijn bewust niet samengevoegd: een
 * kind van acht heeft geen e-mailadres, maar staat wel op de kaart.
 */
class UserDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        $type = in_array($request->string('type')->toString(), ['players', 'trainers', 'guardians'], strict: true)
            ? $request->string('type')->toString()
            : 'players';

        $user = $request->user();

        return Inertia::render('users/Index', [
            'type' => $type,
            'counts' => [
                'players' => Player::active()->count(),
                'trainers' => User::ofCurrentSchool()->role(Role::Trainer->value)->count(),
                'guardians' => User::ofCurrentSchool()->role(Role::Ouder->value)->count(),
            ],
            'can' => [
                'managePlayers' => $user->can('create', Player::class),
                // Accounts uitnodigen en verwijderen is werk van de eigenaar.
                'manageAccounts' => $user->isEigenaar(),
            ],

            ...match ($type) {
                'trainers' => $this->trainers(),
                'guardians' => $this->guardians(),
                default => $this->players($request),
            },
        ]);
    }

    /** @return array<string, mixed> */
    protected function players(Request $request): array
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'position' => (string) $request->string('position'),
            'group' => $request->integer('group') ?: null,
            'status' => (string) $request->string('status', 'active'),
        ];

        $players = Player::query()
            ->with(['groups', 'user'])
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
                'age' => $player->age,
                'is_active' => $player->is_active,
                'overall_rating' => $player->overall_rating,
                'groups' => $player->groups->pluck('name')->all(),
                // Heeft deze speler zelf een inlog, of loopt alles via de ouder?
                'has_login' => $player->user_id !== null,
                'email' => $player->user?->email,
            ]);

        return [
            'players' => $players,
            'filters' => $filters,
            'positions' => PlayerPosition::options(),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    protected function trainers(): array
    {
        $trainers = User::ofCurrentSchool()
            ->role([Role::Trainer->value, Role::Eigenaar->value])
            ->withCount(['trainings', 'reports'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $trainer) => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'email' => $trainer->email,
                'roles' => $trainer->getRoleNames()->all(),
                'is_owner' => $trainer->isEigenaar(),
                'trainings_count' => $trainer->trainings_count,
                'reports_count' => $trainer->reports_count,
            ]);

        return ['trainers' => $trainers];
    }

    /** @return array<string, mixed> */
    protected function guardians(): array
    {
        $guardians = User::ofCurrentSchool()
            ->role(Role::Ouder->value)
            ->with('children')
            ->orderBy('name')
            ->get()
            ->map(fn (User $ouder) => [
                'id' => $ouder->id,
                'name' => $ouder->name,
                'email' => $ouder->email,
                'children' => $ouder->children->map(fn (Player $kind) => [
                    'id' => $kind->id,
                    'name' => $kind->full_name,
                    'relationship' => $kind->pivot->relationship,
                ]),
            ]);

        return ['guardians' => $guardians];
    }
}
