<?php

namespace App\Http\Controllers\Trainings;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Location;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Trainings\VisibleTrainings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De kalender: maand- en weekweergave van de trainingen.
 *
 * Welke trainingen je ziet bepaalt VisibleTrainings, net als in het gewone
 * overzicht - een ouder ziet hier dus ook alleen de groep van zijn kind.
 *
 * De server levert alleen de trainingen in het zichtbare bereik; het raster
 * zelf tekent de browser, want dat is puur presentatie.
 *
 * Daarnaast kies je tussen alle trainingen en alleen de eigen. Die keuze
 * verandert de gegevens en wordt daarom hier gemaakt en in de sessie onthouden
 * - niet in de browser, want dan zou de eerste weergave altijd de verkeerde
 * zijn tot je hem opnieuw aanklikt.
 *
 * Lijst versus raster is géén keuze van de server: dat is dezelfde maand met
 * dezelfde gegevens, alleen anders getekend. Die keuze hoort bij het scherm
 * waarop je kijkt (een raster van zeven kolommen op een telefoon is geen
 * raster) en staat dus in de browser zelf.
 */
class CalendarController extends Controller
{
    public function __construct(protected VisibleTrainings $visible) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Training::class);

        $user = $request->user();

        $view = $request->string('view')->toString() === 'week' ? 'week' : 'month';

        // Alleen wie het hele rooster ziet heeft iets aan de keuze. Voor een
        // ouder of speler filtert VisibleTrainings al op het eigen kind.
        $canChooseScope = $user->isEigenaar() || $user->isTrainer();

        $scope = $this->scope($request, $user, $canChooseScope);

        $datum = rescue(
            fn () => CarbonImmutable::parse((string) $request->string('date', now()->toDateString())),
            fn () => CarbonImmutable::now(),
            report: false,
        )->startOfDay();

        // Weken lopen van maandag tot en met zondag, zoals in Nederland gebruikelijk.
        [$van, $tot] = $view === 'week'
            ? [$datum->startOfWeek(), $datum->endOfWeek()]
            : [$datum->startOfMonth()->startOfWeek(), $datum->endOfMonth()->endOfWeek()];

        $query = $this->visible->query($user)
            ->with(['group', 'trainers'])
            ->whereBetween('starts_at', [$van, $tot]);

        if ($scope === 'mine') {
            $query->forTrainer($user);
        }

        // Groep, trainer en locatie: alleen voor wie het hele rooster ziet.
        // Voor een ouder is er niets te kiezen - hij ziet al alleen zijn kind.
        // Een vreemd id levert gewoon niets op: de global scope filtert de
        // query zelf al op de school.
        $filters = [
            'group' => $canChooseScope ? ($request->integer('group') ?: null) : null,
            'trainer' => $canChooseScope ? ($request->integer('trainer') ?: null) : null,
            'location' => $canChooseScope ? ($request->integer('location') ?: null) : null,
        ];

        if ($filters['group']) {
            $query->where('group_id', $filters['group']);
        }

        if ($filters['trainer']) {
            $query->whereHas('trainers', fn ($q) => $q->where('users.id', $filters['trainer']));
        }

        if ($filters['location']) {
            $query->where('location_id', $filters['location']);
        }

        $trainingen = $query
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Training $training) => [
                'id' => $training->id,
                'date' => $training->starts_at->format('Y-m-d'),
                'starts_at' => $training->starts_at->format('H:i'),
                'ends_at' => $training->ends_at->format('H:i'),
                'group' => $training->label(),
                'location' => $training->location,
                'trainers' => $training->trainers->pluck('name')->all(),
                'has_passed' => $training->hasPassed(),
                'cancelled' => $training->isCancelled(),
                // Zodat je in "Alle trainingen" ziet welke van jou zijn.
                //
                // Hier telt alleen een échte koppeling, en niet de regel dat
                // een training zonder trainers "van iedereen" is. Die regel
                // bepaalt wat je te zien krijgt; zou hij ook het merkteken
                // zetten, dan staat er "jij" bij elke training waar niemand aan
                // gekoppeld is, en dan zegt het merkteken niets meer.
                'is_mine' => $canChooseScope && $training->trainers->contains('id', $user->id),
                'enrollable' => false,
            ]);

        // Voor een ouder: ook de open trainingen waar een van zijn kinderen op
        // past, herkenbaar als "inschrijven". Aantikken opent de inschrijving.
        if (! $canChooseScope && $user->isOuder()) {
            $kinderen = Player::whereIn('id', $user->visiblePlayerIds())->get();

            $open = Training::query()
                ->with(['group', 'trainers'])
                ->where('open_enrollment', true)
                ->whereNull('cancelled_at')
                ->whereBetween('starts_at', [max($van, CarbonImmutable::now()), $tot])
                ->whereNotIn('id', $trainingen->pluck('id'))
                ->orderBy('starts_at')
                ->get()
                ->filter(fn (Training $t) => $t->enrollableChildren($kinderen)->isNotEmpty())
                ->map(fn (Training $training) => [
                    'id' => $training->id,
                    'date' => $training->starts_at->format('Y-m-d'),
                    'starts_at' => $training->starts_at->format('H:i'),
                    'ends_at' => $training->ends_at->format('H:i'),
                    'group' => $training->label(),
                    'location' => $training->location,
                    'trainers' => $training->trainers->pluck('name')->all(),
                    'has_passed' => false,
                    'cancelled' => false,
                    'is_mine' => false,
                    'enrollable' => true,
                    'price' => $training->price_cents > 0 ? $training->formattedPrice() : null,
                    'is_full' => $training->isFull(),
                ]);

            $trainingen = $trainingen->concat($open)->sortBy(fn (array $t) => $t['date'].' '.$t['starts_at'])->values();
        }

        return Inertia::render('calendar/Index', [
            'view' => $view,
            'scope' => $scope,
            'canChooseScope' => $canChooseScope,
            'date' => $datum->toDateString(),
            'today' => now()->toDateString(),
            'range' => ['from' => $van->toDateString(), 'to' => $tot->toDateString()],
            'title' => $view === 'week'
                ? 'Week '.$datum->isoWeek().' · '.$van->translatedFormat('j M').' – '.$tot->translatedFormat('j M Y')
                : ucfirst($datum->translatedFormat('F Y')),
            'trainings' => $trainingen,
            'canManage' => $user->can('create', Training::class),
            'filters' => $filters,
            // De keuzes voor het filterpaneel. Alleen wat er is: een lege
            // keuzelijst is een vraag zonder antwoord.
            'groups' => $canChooseScope ? Group::visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
            'trainers' => $canChooseScope
                ? User::ofCurrentSchool()->role(['trainer', 'eigenaar'])->orderBy('name')->get(['id', 'name'])
                : [],
            'locations' => $canChooseScope ? Location::where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    /**
     * Alle trainingen of alleen de eigen?
     *
     * De keuze uit het verzoek wint en wordt onthouden. Staat er niets in de
     * sessie, dan krijgt een trainer zijn eigen trainingen en een eigenaar het
     * hele rooster: dat is waar ze respectievelijk voor komen.
     */
    protected function scope(Request $request, User $user, bool $canChooseScope): string
    {
        if (! $canChooseScope) {
            return 'all';
        }

        $gekozen = $request->string('scope')->toString();

        if (in_array($gekozen, ['all', 'mine'], true)) {
            $request->session()->put('calendar.scope', $gekozen);

            return $gekozen;
        }

        $onthouden = $request->session()->get('calendar.scope');

        if (in_array($onthouden, ['all', 'mine'], true)) {
            return $onthouden;
        }

        return $user->isTrainer() && ! $user->isEigenaar() ? 'mine' : 'all';
    }
}
