<?php

namespace App\Http\Controllers\Trainings;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainings\TrainingRequest;
use App\Models\Group;
use App\Models\Location;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Trainings\VisibleTrainings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function __construct(protected VisibleTrainings $visible) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Training::class);

        $user = $request->user();
        $eigenSpelers = $user->visiblePlayerIds();
        $magAfvinken = $user->isEigenaar() || $user->isTrainer();

        // Filteren is werk van wie het hele rooster ziet. Een ouder heeft één
        // groep en zou een keuzelijst met één optie krijgen.
        $groep = $magAfvinken ? $request->integer('group') : 0;
        $trainer = $magAfvinken ? $request->integer('trainer') : 0;

        $vorm = fn (Training $training) => [
            'id' => $training->id,
            'group' => $training->label(),
            'group_id' => $training->group_id,
            'day' => $training->starts_at->format('Y-m-d'),
            'day_label' => $training->starts_at->translatedFormat('l j F'),
            'is_today' => $training->starts_at->isToday(),
            'date' => $training->starts_at->translatedFormat('l j F Y'),
            'starts_at' => $training->starts_at->format('H:i'),
            'ends_at' => $training->ends_at->format('H:i'),
            'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
            'location' => $training->location,
            'trainers' => $training->trainers->pluck('name')->all(),
            'is_mine' => $magAfvinken && $training->belongsToTrainer($user),
            'has_passed' => $training->hasPassed(),
            'cancelled' => $training->isCancelled(),
            // Afgevinkt en aanwezig zijn twee verschillende dingen: "niet
            // afgevinkt" is geen "afwezig". Daarom allebei een eigen getal.
            'recorded_count' => $training->attendances_count,
            'present_count' => $training->present_count,
            'expected_count' => $training->group?->players_count ?? $training->expectedPlayers()->count(),
            // Alleen relevant voor ouder en speler: wat gaf ik door?
            'my_registration' => $eigenSpelers === [] ? null : $training->attendances
                ->whereIn('player_id', $eigenSpelers)
                ->first()?->registration?->value,
        ];

        $basis = fn () => $this->visible->query($user)
            ->with('trainers')
            ->withCount([
                'attendances' => fn ($q) => $q->whereNotNull('status'),
                'attendances as present_count' => fn ($q) => $q->where('status', AttendanceStatus::Present->value),
            ])
            ->with(['group' => fn ($q) => $q->withCount(['players' => fn ($p) => $p->where('is_active', true)])])
            ->when($groep > 0, fn ($q) => $q->where('group_id', $groep))
            ->when($trainer > 0, fn ($q) => $q->whereHas('trainers', fn ($t) => $t->whereKey($trainer)))
            ->when($eigenSpelers !== [], fn ($q) => $q->with([
                'attendances' => fn ($a) => $a->whereIn('player_id', $eigenSpelers),
            ]));

        return Inertia::render('trainings/Index', [
            'upcoming' => $basis()->upcoming()->limit(50)->get()->map($vorm),
            'past' => $basis()->past()->limit(20)->get()->map($vorm),
            'canManage' => $user->can('create', Training::class),
            'canRecord' => $magAfvinken,
            'isParticipant' => $eigenSpelers !== [],
            'filters' => ['group' => $groep ?: null, 'trainer' => $trainer ?: null],
            // Alleen tonen waar iets uit te kiezen valt.
            'groups' => $magAfvinken
                ? Group::orderBy('name')->get(['id', 'name'])->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])
                : [],
            'trainers' => $magAfvinken ? $this->beschikbareTrainers() : [],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Training::class);

        return Inertia::render('trainings/Form', $this->formData(null));
    }

    public function store(TrainingRequest $request): RedirectResponse
    {
        $gegevens = $request->trainingData();
        $herhaalTot = $request->validated('repeat_until');

        $trainingen = collect($request->occurrences($gegevens['starts_at'], $gegevens['ends_at'], $herhaalTot))
            ->map(function (array $moment) use ($gegevens, $request) {
                $training = Training::create([
                    'group_id' => $gegevens['group_id'],
                    'starts_at' => $moment['starts_at'],
                    'ends_at' => $moment['ends_at'],
                    'location_id' => $gegevens['location_id'],
                    'location' => $gegevens['location'],
                    'note' => $gegevens['note'],
                ]);

                $training->trainers()->sync($request->validated('trainers') ?? []);

                return $training;
            });

        $melding = $trainingen->count() === 1
            ? 'De training is ingepland.'
            : "Er zijn {$trainingen->count()} trainingen ingepland.";

        return redirect()->route('trainings.index')->with('status', $melding);
    }

    public function show(Request $request, Training $training): Response
    {
        $this->authorize('view', $training);

        $user = $request->user();
        $mag = $user->can('recordAttendance', $training);

        $aanwezigheid = $training->attendances()->get()->keyBy('player_id');

        // Een trainer ziet de hele groep; een ouder of speler alleen het eigen kind.
        $spelers = $training->expectedPlayers();

        if (! $mag) {
            $spelers = $spelers->whereIn('id', $user->visiblePlayerIds());
        }

        return Inertia::render('trainings/Show', [
            'training' => [
                'id' => $training->id,
                'group' => $training->label(),
                'group_id' => $training->group_id,
                'date' => $training->starts_at->translatedFormat('l j F Y'),
                'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
                'location' => $training->location,
                'location_id' => $training->location_id,
                'note' => $training->note,
                'trainers' => $training->trainers->map(fn (User $trainer) => [
                    'id' => $trainer->id,
                    'name' => $trainer->name,
                ]),
                'has_passed' => $training->hasPassed(),
                'cancelled_at' => $training->cancelled_at?->format('d-m-Y H:i'),
                'cancellation_reason' => $training->cancellation_reason,
            ],
            'players' => $spelers->values()->map(fn (Player $speler) => [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'position' => $speler->position->label(),
                'registration' => $aanwezigheid->get($speler->id)?->registration?->value,
                'status' => $aanwezigheid->get($speler->id)?->status?->value,
            ]),
            'can' => [
                'record' => $mag,
                'manage' => $user->can('update', $training),
                'delete' => $user->can('delete', $training),
            ],
        ]);
    }

    public function edit(Training $training): Response
    {
        $this->authorize('update', $training);

        return Inertia::render('trainings/Form', $this->formData($training));
    }

    public function update(TrainingRequest $request, Training $training): RedirectResponse
    {
        $training->update($request->trainingData());
        $training->trainers()->sync($request->validated('trainers') ?? []);

        return redirect()
            ->route('trainings.show', $training)
            ->with('status', 'De training is opgeslagen.');
    }

    public function destroy(Training $training): RedirectResponse
    {
        $this->authorize('delete', $training);

        $training->delete();

        return redirect()
            ->route('trainings.index')
            ->with('status', 'De training is verwijderd.');
    }

    /**
     * Wie kun je aan een training hangen: trainers en de eigenaar.
     *
     * De eigenaar staat er bewust bij — bij kleine scholen geeft die zelf ook
     * training.
     */
    protected function beschikbareTrainers()
    {
        return User::ofCurrentSchool()
            ->role([Role::Trainer->value, Role::Eigenaar->value])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
    }

    /** @return array<string, mixed> */
    protected function formData(?Training $training): array
    {
        return [
            'training' => $training ? [
                'id' => $training->id,
                'group_id' => $training->group_id,
                'date' => $training->starts_at->format('Y-m-d'),
                'starts_at' => $training->starts_at->format('H:i'),
                'ends_at' => $training->ends_at->format('H:i'),
                'location' => $training->location,
                'note' => $training->note,
                'trainers' => $training->trainers->pluck('id')->all(),
            ] : null,
            'availableTrainers' => $this->beschikbareTrainers(),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Location $locatie) => ['id' => $locatie->id, 'name' => $locatie->name]),
            'groups' => Group::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'age_category']),
        ];
    }
}
