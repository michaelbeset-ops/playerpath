<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personeel: wie er bij de school wérkt.
 *
 * Staat onder "Mijn bedrijf" en niet bij de klanten. Een trainer is geen klant,
 * en hem tussen de spelers en ouders zetten maakt beide lijsten onbruikbaar.
 */
class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        $trainers = User::ofCurrentSchool()
            ->role([Role::Trainer->value, Role::Eigenaar->value])
            ->withCount(['trainings', 'reports'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $trainer) => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'photo' => $trainer->photo_url,
                'email' => $trainer->email,
                'roles' => $trainer->getRoleNames()->all(),
                'is_owner' => $trainer->isEigenaar(),
                'trainings_count' => $trainer->trainings_count,
                'reports_count' => $trainer->reports_count,
            ]);

        return Inertia::render('staff/Index', [
            'trainers' => $trainers,
            // Wie er is uitgenodigd maar nog niet binnen. Zonder dit lijstje
            // weet een school na een bulkuitnodiging niet wie er nog moet.
            'invitations' => Invitation::where('role', Role::Trainer->value)
                ->pending()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Invitation $rij) => [
                    'id' => $rij->id,
                    'name' => $rij->name,
                    'email' => $rij->email,
                    'status' => $rij->status(),
                    'expires_on' => $rij->expires_at->format('d-m-Y'),
                    'sent_count' => $rij->sent_count,
                ]),
            'invitationDays' => (int) ($request->user()->school->invitation_valid_days ?: 14),
            // Accounts uitnodigen en verwijderen is werk van de eigenaar.
            'can' => ['manageAccounts' => $request->user()->isEigenaar()],
        ]);
    }
}
