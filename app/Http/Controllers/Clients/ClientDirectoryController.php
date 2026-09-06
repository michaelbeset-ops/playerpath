<?php

namespace App\Http\Controllers\Clients;

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
 * Klanten: de spelers en hun ouders.
 *
 * "Klanten" en niet "Gebruikers", omdat een school in mensen denkt en niet in
 * accounts. Trainers horen hier niet bij; die staan onder Personeel — een
 * trainer is geen klant, en zoeken tussen de klanten naar je eigen collega's
 * is precies de verwarring die dat menu-item veroorzaakte.
 *
 * Let op het verschil dat hieronder overal doorwerkt: een **speler** is een
 * profiel (tabel `players`) met een optioneel eigen inlogaccount; een **ouder**
 * is altijd een account (tabel `users`). Die twee zijn bewust niet
 * samengevoegd: een kind van acht heeft geen e-mailadres, maar staat wel op
 * de kaart.
 */
class ClientDirectoryController extends Controller
{
    public function players(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

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
                'photo' => $player->photo_url,
                'position' => $player->position->label(),
                'age' => $player->age,
                'is_active' => $player->is_active,
                'overall_rating' => $player->overall_rating,
                'groups' => $player->groups->pluck('name')->all(),
                // Heeft deze speler zelf een inlog, of loopt alles via de ouder?
                'has_login' => $player->user_id !== null,
                'email' => $player->user?->email,
            ]);

        return Inertia::render('clients/Players', [
            ...$this->gedeeld($request),
            'players' => $players,
            'filters' => $filters,
            'positions' => PlayerPosition::options(),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function guardians(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        $guardians = User::ofCurrentSchool()
            ->role(Role::Ouder->value)
            ->with('children')
            ->orderBy('name')
            ->get()
            ->map(fn (User $ouder) => [
                'id' => $ouder->id,
                'name' => $ouder->name,
                'photo' => $ouder->photo_url,
                'email' => $ouder->email,
                'children' => $ouder->children->map(fn (Player $kind) => [
                    'id' => $kind->id,
                    'name' => $kind->full_name,
                    'relationship' => $kind->pivot->relationship,
                ]),
            ]);

        return Inertia::render('clients/Guardians', [
            ...$this->gedeeld($request),
            'guardians' => $guardians,
        ]);
    }

    /**
     * Wat op beide tabbladen staat: de tellingen en wat je mag.
     *
     * @return array<string, mixed>
     */
    protected function gedeeld(Request $request): array
    {
        return [
            'counts' => [
                'players' => Player::active()->count(),
                'guardians' => User::ofCurrentSchool()->role(Role::Ouder->value)->count(),
            ],
            'can' => [
                'managePlayers' => $request->user()->can('create', Player::class),
            ],
        ];
    }
}
