<?php

namespace App\Http\Controllers;

use App\Enums\DashboardWidget;
use App\Models\Group;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Dashboard\AttentionItems;
use App\Support\Dashboard\DashboardTrends;
use App\Support\Dashboard\DevelopmentOverview;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Dashboard\SetupChecklist;
use App\Support\Dashboard\WidgetRegistry;
use App\Support\Goals\GoalProgress;
use App\Support\Money\Money;
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
 * ## De schoolweergave is opgebouwd uit widgets
 *
 * Een dashboard beantwoordt twee vragen, in deze volgorde: "hoe gaat het?" en
 * "wat moet ik doen?". Vandaar de volgorde op het scherm: snelle acties, dan
 * het aandacht-blok, dan de cijfers, dan de verdieping.
 *
 * Het **aandacht-blok staat vast** bovenaan en is geen widget: het is het
 * antwoord op de tweede vraag, en dat hoort niet weg te klikken te zijn.
 * Al het andere komt uit `WidgetRegistry`, en **er wordt alleen berekend wat er
 * ook staat** — een widget die iemand heeft weggehaald kost geen enkele query.
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
        protected WidgetRegistry $widgets,
        protected AttentionItems $attention,
        protected DashboardTrends $trends,
        protected DevelopmentOverview $development,
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
        $layout = $this->widgets->layoutFor($user);
        $zichtbaar = array_column($layout, 'key');
        $toont = fn (DashboardWidget $widget) => in_array($widget->value, $zichtbaar, true);

        $aandacht = $this->attention->for($user);
        $aandachtVingerafdruk = $this->attention->signature($aandacht);

        // Eén keer ophalen voor alle vier de kerncijfers; ze delen hun bron.
        $trends = array_intersect($zichtbaar, ['kpi_players', 'kpi_rating', 'kpi_reports', 'kpi_revenue']) !== []
            ? $this->trends->all()
            : [];

        return Inertia::render('Dashboard', [
            'view' => 'school',
            // Verdwijnt zodra de school draait; zie SetupChecklist. Bewust
            // geen widget: wie hem wegklikt weet nooit meer wat er nog moet.
            'checklist' => $this->checklist->for($user),
            // Het antwoord op "wat moet ik doen?". Staat vast bovenaan, en
            // blijft weg zolang er hetzelfde in staat als toen je het wegklikte.
            'attention' => $aandacht,
            'attentionSignature' => $aandachtVingerafdruk,
            // Weggeklikt: dan verdwijnt het blok helemaal. "Alles loopt" tonen
            // terwijl er zeven rekeningen openstaan zou een leugen zijn.
            'attentionDismissed' => $user->heeftAandachtWeggeklikt($aandachtVingerafdruk),
            'layout' => $layout,
            // Wat je erbij kunt zetten in de bewerkmodus. Alleen wat deze rol
            // mag zien; wat hier niet in staat kan ook niet opgeslagen worden.
            'availableWidgets' => $this->widgets->describe($user),
            'widgets' => [
                'kpi_players' => $toont(DashboardWidget::KpiPlayers) ? $trends['players'] : null,
                'kpi_rating' => $toont(DashboardWidget::KpiRating) ? $trends['rating'] : null,
                'kpi_reports' => $toont(DashboardWidget::KpiReports) ? $trends['reports'] : null,
                'kpi_revenue' => $toont(DashboardWidget::KpiRevenue) ? $this->omzet($trends['revenue'] ?? []) : null,
                'development' => $toont(DashboardWidget::Development) ? $this->development->for() : null,
                'finance' => $toont(DashboardWidget::Finance) ? $this->financieel() : null,
                'trainings' => $toont(DashboardWidget::Trainings) ? $this->dashboard->upcomingTrainings(3) : null,
                'birthdays' => $toont(DashboardWidget::Birthdays) ? $this->dashboard->birthdays(limit: 4) : null,
            ],
            'can' => [
                'managePlayers' => $user->can('create', Player::class),
                'manageGroups' => $user->can('create', Group::class),
                'planTrainings' => $user->can('create', Training::class),
            ],
        ]);
    }

    /**
     * De omzettegel. Centen komen als geformatteerd bedrag naar buiten; de
     * trend is een percentage, want een verschil in euro's zegt niets zonder
     * te weten waarvan.
     *
     * @param  array<string, mixed>  $ruw
     * @return array<string, mixed>
     */
    protected function omzet(array $ruw): array
    {
        return [
            'value' => Money::format($ruw['cents'] ?? 0),
            'change' => $ruw['change'] ?? null,
            'unit' => 'procent',
            'hint' => $ruw['hint'] ?? null,
        ];
    }

    /**
     * Het financiële vak, compacter dan het was.
     *
     * "Omzet deze maand" staat er bewust niet in: dat is een kerncijfer
     * bovenaan, en elk cijfer hoort op precies één plek te staan.
     *
     * @return array<string, mixed>
     */
    protected function financieel(): array
    {
        $samenvatting = $this->billing->summary();

        return [
            'outstanding' => $samenvatting['outstanding'],
            'outstandingCount' => $samenvatting['outstandingCount'],
            'activeSubscriptions' => $samenvatting['activeSubscriptions'],
            'yearlyValue' => $samenvatting['yearlyValue'],
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
            ],
        ];
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
                    'photo' => $speler->photo_url,
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
