<?php

namespace App\Http\Controllers\Clients;

use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Features\Features;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Klanten: de spelers, met hun ouders eronder.
 *
 * "Klanten" en niet "Gebruikers", omdat een school in mensen denkt en niet in
 * accounts. Trainers horen hier niet bij; die staan onder Personeel — een
 * trainer is geen klant, en zoeken tussen de klanten naar je eigen collega's
 * is precies de verwarring die dat menu-item veroorzaakte.
 *
 * ## Eén lijst, geen twee
 *
 * Spelers en ouders stonden op twee tabbladen. Maar een school denkt niet in
 * "een ouder": ze denkt in een kind, en bij dat kind hoort iemand die je belt
 * als de training uitvalt. Vandaar één lijst met de speler als regel en de
 * ouders uitklapbaar eronder.
 *
 * Dat is **alleen een samenvoeging in de weergave**. Het onderscheid blijft
 * onder water precies zoals het was: een **speler** is een profiel (tabel
 * `players`) met een optioneel eigen inlogaccount; een **ouder** is altijd een
 * account (tabel `users`). Een kind van acht heeft geen e-mailadres, maar
 * staat wel op de kaart.
 */
class ClientDirectoryController extends Controller
{
    public function __construct(protected Features $features) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'position' => (string) $request->string('position'),
            'group' => $request->integer('group') ?: null,
            'status' => (string) $request->string('status', 'active'),
        ];

        // Zonder de betaallaag is "betaling openstaand" een status die nergens
        // vandaan komt; dan wordt hij ook niet berekend.
        $betalingen = $this->features->enabled(Feature::Betalingen);
        $trainer = $request->user()->isTrainer() && ! $request->user()->isEigenaar();

        $players = Player::query()
            // Een trainer ziet zijn eigen spelers; de eigenaar alles.
            ->visibleTo($request->user())
            ->with(['groups', 'user', 'guardians'])
            // Wanneer voor het laatst beoordeeld: dat is de vraag waarmee een
            // trainer deze lijst opent. Dezelfde grens van dertig dagen als
            // overal, zodat "te lang geleden" één ding betekent.
            ->withMax('reports', 'reported_on')
            ->when($betalingen, fn ($q) => $q->withCount([
                // Te laat, niet "openstaand": een rekening die volgende maand
                // vervalt vraagt nergens om en zou de hele lijst oranje maken.
                'payments as overdue_count' => fn ($p) => $p
                    ->where('status', PaymentStatus::Open->value)
                    ->whereDate('due_on', '<', now()->toDateString()),
            ]))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = '%'.$filters['search'].'%';

                // Ook op de naam van een ouder: wie een mailtje van "Marieke"
                // krijgt, zoekt op Marieke en niet op de achternaam van haar zoon.
                $query->where(fn ($q) => $q
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereHas('guardians', fn ($g) => $g
                        ->where('users.name', 'like', $term)
                        ->orWhere('users.email', 'like', $term)));
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
                'is_demo' => $player->is_demo,
                'overall_rating' => $player->overall_rating,
                'days_since_report' => $player->reports_max_reported_on === null
                    ? null
                    : (int) CarbonImmutable::parse($player->reports_max_reported_on)->startOfDay()->diffInDays(now()->startOfDay()),
                'groups' => $player->groups->pluck('name')->all(),
                // Heeft deze speler zelf een inlog, of loopt alles via de ouder?
                'has_login' => $player->user_id !== null,
                'email' => $player->user?->email,
                'has_overdue_payment' => $betalingen && $player->overdue_count > 0,
                // De ouders horen bij de klantrelatie, niet bij het trainen. Een
                // trainer krijgt ze niet mee — ook niet onzichtbaar in de JSON.
                'guardians' => $trainer ? collect() : $player->guardians->map(fn (User $ouder) => [
                    'id' => $ouder->id,
                    'name' => $ouder->name,
                    'photo' => $ouder->photo_url,
                    'email' => $ouder->email,
                    'relationship' => $ouder->pivot->relationship,
                ])->values(),
            ]);

        return Inertia::render('clients/Index', [
            'players' => $players,
            // Een trainer heeft geen klanten, hij heeft spelers. Zelfde scherm,
            // ander woord — en zonder ouders uitklapbaar eronder.
            'isTrainer' => $trainer,
            'staleAfterDays' => SchoolDashboard::AANDACHT_NA_DAGEN,
            'filters' => $filters,
            'positions' => PlayerPosition::options(),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
            'counts' => [
                'players' => Player::active()->count(),
                'guardians' => User::ofCurrentSchool()->role(Role::Ouder->value)->count(),
            ],
            'can' => [
                'managePlayers' => $request->user()->can('create', Player::class),
            ],
        ]);
    }
}
