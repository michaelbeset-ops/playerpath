<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Dashboard\SetupChecklist;
use App\Support\Goals\GoalProgress;
use App\Support\Payments\BillingOverview;
use App\Support\Payments\PaymentGateway;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Trainings\VisibleTrainings;
use Illuminate\Http\RedirectResponse;
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
 * De cijfers voor de schoolweergave staan in Support/Dashboard/SchoolDashboard.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected VisibleTrainings $visible,
        protected SchoolDashboard $dashboard,
        protected BillingOverview $billing,
        protected PaymentGateway $gateway,
        protected CalculatePlayerCard $calculator,
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected GoalProgress $goals,
        protected SetupChecklist $checklist,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Een platformbeheerder hoort niet bij een school en heeft hier dus
        // niets te zoeken; zijn werkvloer is /beheer. Eén school bekijken doet
        // hij via impersonatie, en dan heeft hij wél een school.
        if ($user->isPlatformbeheerder() && $user->school_id === null) {
            return redirect()->route('platform.dashboard');
        }

        $eigenSpelers = $user->visiblePlayerIds();

        return $eigenSpelers === []
            ? $this->voorSchool($user)
            : $this->voorGezin($user, $eigenSpelers);
    }

    /** Eigenaar en trainer: de cijfers van de school. */
    protected function voorSchool(User $user): Response
    {
        return Inertia::render('Dashboard', [
            'view' => 'school',
            // Verdwijnt zodra de school draait; zie SetupChecklist.
            'checklist' => $this->checklist->for($user),
            'stats' => $this->dashboard->stats(),
            'needsAttention' => $this->dashboard->needsAttention(),
            'attentionAfterDays' => SchoolDashboard::AANDACHT_NA_DAGEN,
            'upcomingTrainings' => $this->dashboard->upcomingTrainings(),
            'can' => [
                'managePlayers' => $user->can('create', Player::class),
                'manageGroups' => $user->can('create', Group::class),
                'planTrainings' => $user->can('create', Training::class),
                // Het financiële overzicht is van de eigenaar, niet van de trainer.
                'seeFinance' => $user->isEigenaar(),
            ],
            // Het financiële vak. De cijfers komen uit de administratie; of er
            // ook echt geïncasseerd wordt hangt af van de gateway.
            'finance' => $user->isEigenaar() ? $this->billing->summary() : null,
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
                'message' => $this->gateway->statusMessage(),
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
                $badges = $this->badges->for($speler, $this->progress);

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'first_name' => $speler->first_name,
                    'position' => $speler->position->label(),
                    'position_key' => $speler->position->value,
                    'age' => $speler->age,
                    'overall_rating' => $speler->overall_rating,
                    'last_report_on' => $laatste?->reported_on->format('d-m-Y'),
                    'report_count' => $speler->reports()->count(),
                    // De kaart zelf op het dashboard: dat is waar een kind voor komt.
                    'categories' => $this->calculator->breakdown($speler),
                    'level' => $this->badges->level($speler->overall_rating),
                    'badges' => array_values(array_filter($badges, fn ($b) => $b['earned'])),
                    // De eerstvolgende mijlpaal: iets om naartoe te werken.
                    'next_badge' => collect($badges)->first(fn ($b) => ! $b['earned']),
                    'goals' => array_values(array_filter($this->goals->forPlayer($speler), fn ($d) => $d['status'] === 'active')),
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
