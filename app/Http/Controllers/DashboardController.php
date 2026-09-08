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
use App\Support\Dashboard\FamilyDashboard;
use App\Support\Dashboard\PlayerDashboard;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Dashboard\SetupChecklist;
use App\Support\Dashboard\Signal;
use App\Support\Dashboard\TrainerDashboard;
use App\Support\Dashboard\WidgetRegistry;
use App\Support\Goals\GoalProgress;
use App\Support\Money\Money;
use App\Support\Payments\BillingOverview;
use App\Support\Payments\PaymentGateway;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerCardPresenter;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Trainings\ReportPrompts;
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
        protected PlayerBadges $badges,
        protected PlayerCardPresenter $presenter,
        protected PlayerProgress $progress,
        protected GoalProgress $goals,
        protected SetupChecklist $checklist,
        protected WidgetRegistry $widgets,
        protected AttentionItems $attention,
        protected DashboardTrends $trends,
        protected DevelopmentOverview $development,
        protected ReportPrompts $prompts,
        protected FamilyDashboard $family,
        protected PlayerDashboard $speler,
        protected TrainerDashboard $trainer,
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

        if ($eigenSpelers === []) {
            return $this->voorSchool($user);
        }

        // Een speler met een eigen inlog ziet alleen zichzelf: de kaart, de
        // voortgang en de volgende training. Inschrijven en betalen zijn van
        // zijn ouders.
        if ($user->isSpeler() && ! $user->isOuder()) {
            return $this->voorSpeler($user, Player::findOrFail($eigenSpelers[0]));
        }

        return $this->voorGezin($user, $eigenSpelers);
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
        // Ook het financiële vak leunt op deze cijfers, dus die telt mee in de
        // vraag of ze opgehaald moeten worden.
        $trends = array_intersect($zichtbaar, ['kpi_players', 'kpi_rating', 'kpi_reports', 'kpi_revenue', 'finance']) !== []
            ? $this->trends->all()
            : [];

        return Inertia::render('Dashboard', [
            'view' => 'school',
            // Verdwijnt zodra de school draait; zie SetupChecklist. Bewust
            // geen widget: wie hem wegklikt weet nooit meer wat er nog moet.
            'checklist' => $this->checklist->for($user),
            // Het antwoord op "wat moet ik doen?". Staat vast bovenaan, en
            // blijft weg zolang er hetzelfde in staat als toen je het wegklikte.
            // Tijdgebonden en dus het allereerste: over vijf uur is het weg.
            'reportPrompts' => $this->prompts->for($user),
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
                'finance' => $toont(DashboardWidget::Finance)
                    ? $this->financieel($trends['revenue'] ?? [], $toont(DashboardWidget::KpiRevenue))
                    : null,
                'trainings' => $toont(DashboardWidget::Trainings) ? $this->dashboard->upcomingTrainings(3) : null,
                'birthdays' => $toont(DashboardWidget::Birthdays) ? $this->dashboard->birthdays(limit: 3, for: $user) : null,
                // Van de trainer. Alleen berekend als ze er ook staan; een
                // eigenaar die niet traint kost dit dus geen enkele query.
                'my_trainings' => $toont(DashboardWidget::MyTrainings) ? $this->trainer->trainings($user) : null,
                'my_players' => $toont(DashboardWidget::MyPlayers) ? $this->trainer->players($user) : null,
            ],
            'can' => [
                'managePlayers' => $user->can('create', Player::class),
                'manageGroups' => $user->can('create', Group::class),
                'planTrainings' => $user->can('create', Training::class),
            ],
            // De rol bepaalt de kop en de snelle acties: een trainer krijgt
            // "Mijn trainingen" waar een eigenaar "Speler toevoegen" krijgt.
            'isTrainerOnly' => $user->isTrainer() && ! $user->isEigenaar(),
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
            'tone' => $ruw['tone'] ?? Signal::NEUTRAL,
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
    protected function financieel(array $omzet, bool $omzetStaatBovenaan): array
    {
        $samenvatting = $this->billing->summary();

        return [
            // Omzet staat hier alleen als hij niet al als kerncijfer bovenaan
            // staat: elk cijfer hoort op precies één plek. Zonder die tegel is
            // dit vak de enige plek waar een eigenaar zijn omzet ziet.
            'revenue' => $omzetStaatBovenaan ? null : [
                'thisMonth' => Money::format($omzet['cents'] ?? 0),
                'lastMonth' => Money::format($omzet['previousCents'] ?? 0),
                'change' => $omzet['change'] ?? null,
                'tone' => $omzet['tone'] ?? Signal::NEUTRAL,
            ],
            'outstanding' => $samenvatting['outstanding'],
            'outstandingCount' => $samenvatting['outstandingCount'],
            // Openstaand is geen ramp, maar wel iets om te zien. Nul is goed
            // nieuws en hoort dus niet oranje te zijn.
            'outstandingTone' => Signal::count($samenvatting['outstandingCount']),
            'activeSubscriptions' => $samenvatting['activeSubscriptions'],
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
            ],
        ];
    }

    /** De speler zelf: mijn kaart, hoe ga ik vooruit, wanneer is de training. */
    protected function voorSpeler(User $user, Player $speler): Response
    {
        return Inertia::render('Dashboard', [
            'view' => 'speler',
            ...$this->speler->for($user, $speler),
        ]);
    }

    /**
     * Het dashboard van een ouder.
     *
     * Praktisch bovenaan, de spelerskaart klein. Een ouder komt hier om te
     * weten wanneer de training is, of hij nog moet betalen en of er nieuws is;
     * de kaart is het mooiste wat dit product maakt, maar hij beantwoordt geen
     * van die vragen. Eén tik op een kindkaartje opent hem alsnog helemaal.
     *
     * @param  list<int>  $spelerIds
     */
    protected function voorGezin(User $user, array $spelerIds): Response
    {
        return Inertia::render('Dashboard', [
            'view' => 'gezin',
            'children' => $this->family->children($spelerIds),
            'upcoming' => $this->family->upcomingTrainings($user, $spelerIds),
            'offerings' => $this->family->openOfferings($user),
            'messages' => $this->family->messages($user),
        ]);
    }
}
