<?php

namespace App\Http\Controllers\Trainings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trainings\TrainingRequest;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Player;
use App\Models\Training;
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

        $vorm = fn (Training $training) => [
            'id' => $training->id,
            'group' => $training->group->name,
            'date' => $training->starts_at->translatedFormat('l j F Y'),
            'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
            'location' => $training->location,
            'attendance_count' => $training->attendances_count,
            'expected_count' => $training->group->players_count,
            // Alleen relevant voor ouder en speler: wat gaf ik door?
            'my_registration' => $eigenSpelers === [] ? null : $training->attendances
                ->whereIn('player_id', $eigenSpelers)
                ->first()?->registration?->value,
        ];

        $basis = fn () => $this->visible->query($user)
            ->withCount(['attendances' => fn ($q) => $q->whereNotNull('status')])
            ->with(['group' => fn ($q) => $q->withCount(['players' => fn ($p) => $p->where('is_active', true)])])
            ->when($eigenSpelers !== [], fn ($q) => $q->with([
                'attendances' => fn ($a) => $a->whereIn('player_id', $eigenSpelers),
            ]));

        return Inertia::render('trainings/Index', [
            'upcoming' => $basis()->upcoming()->limit(50)->get()->map($vorm),
            'past' => $basis()->past()->limit(20)->get()->map($vorm),
            'canManage' => $user->can('create', Training::class),
            'isParticipant' => $eigenSpelers !== [],
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
            ->map(fn (array $moment) => Training::create([
                'group_id' => $gegevens['group_id'],
                'starts_at' => $moment['starts_at'],
                'ends_at' => $moment['ends_at'],
                'location' => $gegevens['location'],
                'note' => $gegevens['note'],
            ]));

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
                'group' => $training->group->name,
                'group_id' => $training->group_id,
                'date' => $training->starts_at->translatedFormat('l j F Y'),
                'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
                'location' => $training->location,
                'note' => $training->note,
                'has_passed' => $training->hasPassed(),
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
            ] : null,
            'groups' => Group::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'age_category']),
        ];
    }
}
