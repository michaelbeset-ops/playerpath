<?php

namespace App\Http\Controllers\Trainings;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Support\Trainings\VisibleTrainings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De kalender: maand- en weekweergave van de trainingen.
 *
 * Welke trainingen je ziet bepaalt VisibleTrainings, net als in het gewone
 * overzicht — een ouder ziet hier dus ook alleen de groep van zijn kind.
 *
 * De server levert alleen de trainingen in het zichtbare bereik; het raster
 * zelf tekent de browser, want dat is puur presentatie.
 */
class CalendarController extends Controller
{
    public function __construct(protected VisibleTrainings $visible) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Training::class);

        $view = $request->string('view')->toString() === 'week' ? 'week' : 'month';

        $datum = rescue(
            fn () => CarbonImmutable::parse((string) $request->string('date', now()->toDateString())),
            fn () => CarbonImmutable::now(),
            report: false,
        )->startOfDay();

        // Weken lopen van maandag tot en met zondag, zoals in Nederland gebruikelijk.
        [$van, $tot] = $view === 'week'
            ? [$datum->startOfWeek(), $datum->endOfWeek()]
            : [$datum->startOfMonth()->startOfWeek(), $datum->endOfMonth()->endOfWeek()];

        $trainingen = $this->visible->query($request->user())
            ->with(['group', 'trainers'])
            ->whereBetween('starts_at', [$van, $tot])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Training $training) => [
                'id' => $training->id,
                'date' => $training->starts_at->format('Y-m-d'),
                'starts_at' => $training->starts_at->format('H:i'),
                'ends_at' => $training->ends_at->format('H:i'),
                'group' => $training->group->name,
                'location' => $training->location,
                'trainers' => $training->trainers->pluck('name')->all(),
                'has_passed' => $training->hasPassed(),
            ]);

        return Inertia::render('calendar/Index', [
            'view' => $view,
            'date' => $datum->toDateString(),
            'today' => now()->toDateString(),
            'range' => ['from' => $van->toDateString(), 'to' => $tot->toDateString()],
            'title' => $view === 'week'
                ? 'Week '.$datum->isoWeek().' · '.$van->translatedFormat('j M').' – '.$tot->translatedFormat('j M Y')
                : ucfirst($datum->translatedFormat('F Y')),
            'trainings' => $trainingen,
            'canManage' => $request->user()->can('create', Training::class),
        ]);
    }
}
