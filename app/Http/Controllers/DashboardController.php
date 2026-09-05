<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use App\Models\User;
use App\Support\Trainings\VisibleTrainings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het dashboard verschilt per rol, en dat is geen cosmetica.
 *
 * Een eigenaar of trainer kijkt naar de school; een ouder of speler naar zijn
 * eigen kind. Eén gedeeld dashboard toonde een ouder schoolbrede cijfers en
 * een knop "Rapport invullen" die hij toch niet mag gebruiken.
 *
 * Het volledige eigenaar-dashboard (omzet, overzichten) is fase 6.
 */
class DashboardController extends Controller
{
    public function __construct(protected VisibleTrainings $visible) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $eigenSpelers = $user->visiblePlayerIds();

        return $eigenSpelers === []
            ? $this->voorSchool()
            : $this->voorGezin($user, $eigenSpelers);
    }

    /** Eigenaar en trainer: de cijfers van de school. */
    protected function voorSchool(): Response
    {
        return Inertia::render('Dashboard', [
            'view' => 'school',
            'stats' => [
                'players' => Player::active()->count(),
                'reportsThisWeek' => Report::where('reported_on', '>=', now()->startOfWeek())->count(),
                'trainingsThisWeek' => Training::whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
        ]);
    }

    /**
     * Ouder en speler: het eigen kind.
     *
     * @param  list<int>  $spelerIds
     */
    protected function voorGezin(User $user, array $spelerIds): Response
    {
        $spelers = Player::whereIn('id', $spelerIds)
            ->orderBy('first_name')
            ->get()
            ->map(function (Player $speler) {
                $laatste = $speler->reports()->newestFirst()->first();

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'first_name' => $speler->first_name,
                    'position' => $speler->position->label(),
                    'overall_rating' => $speler->overall_rating,
                    'last_report_on' => $laatste?->reported_on->format('d-m-Y'),
                ];
            });

        $volgende = $this->visible->query($user)->upcoming()->first();

        return Inertia::render('Dashboard', [
            'view' => 'gezin',
            'players' => $spelers,
            'nextTraining' => $volgende ? [
                'id' => $volgende->id,
                'group' => $volgende->group->name,
                'date' => $volgende->starts_at->translatedFormat('l j F'),
                'time' => $volgende->starts_at->format('H:i').' - '.$volgende->ends_at->format('H:i'),
                'location' => $volgende->location,
            ] : null,
        ]);
    }
}
