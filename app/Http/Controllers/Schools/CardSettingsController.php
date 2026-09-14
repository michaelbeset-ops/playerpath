<?php

namespace App\Http\Controllers\Schools;

use App\Actions\Schools\ChangeCardMode;
use App\Http\Controllers\Controller;
use App\Models\CourseAssessment;
use App\Models\EffortRating;
use App\Models\Report;
use App\Support\Rating\Grade;
use App\Support\Rating\RatingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mijn bedrijf → Spelerskaart: welke kaart, en de knoppen die erbij horen.
 *
 * De keuze zelf (prestatiekaart of inzetkaart) staat ook in de wizard; dit is
 * dezelfde keuze later, met een waarschuwing over wat er met bestaande
 * gegevens gebeurt. Daaronder per kaart wat een school kan instellen: kleuren
 * of cijfers bij de prestatiekaart; puntenwaarden, treden en de kleurenschaal
 * bij de inzetkaart. Alleen de eigenaar.
 */
class CardSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $instellingen = RatingSettings::for($request->user()->school);

        return Inertia::render('schools/CardSettings', [
            'mode' => $instellingen->cardMode(),
            'grading' => $instellingen->grading(),
            'attendancePoints' => $instellingen->xpForAttendance(),
            'effortLevels' => $instellingen->effortLevels(),
            'attitudeLevels' => $instellingen->attitudeLevels(),
            'progressLevels' => $instellingen->progressLevels(),
            'palette' => RatingSettings::KLEURENPALET,
            // Voor de waarschuwing bij wisselen: wat er al is vastgelegd.
            'counts' => [
                'reports' => Report::query()->real()->count(),
                'efforts' => EffortRating::query()->real()->count(),
                'courses' => CourseAssessment::query()->count(),
            ],
        ]);
    }

    public function mode(Request $request, ChangeCardMode $wissel): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $data = $request->validate([
            'mode' => ['required', Rule::in([RatingSettings::PRESTATIE, RatingSettings::INZET])],
        ], [], ['mode' => 'De spelerskaart']);

        $gewisseld = $wissel->handle($request->user()->school, $data['mode']);

        if (! $gewisseld) {
            return back();
        }

        return back()->with('status', $data['mode'] === RatingSettings::INZET
            ? 'Jullie werken nu met de inzetkaart. De punten zijn opnieuw opgeteld: alleen aanwezigheid en inzet tellen mee.'
            : 'Jullie werken nu met de prestatiekaart. De punten zijn opnieuw opgeteld: aanwezigheid, rapporten en groei tellen mee.');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $data = $request->validate([
            'grading' => ['required', Rule::in([Grade::KLEUREN, Grade::CIJFERS])],
            'attendance_points' => ['required', 'integer', 'min:0', 'max:100'],
            'effort_levels' => ['required', 'array', 'min:3', 'max:4'],
            'effort_levels.*.label' => ['required', 'string', 'max:30'],
            'effort_levels.*.points' => ['required', 'integer', 'min:1', 'max:100'],
            'attitude_levels' => ['required', 'array', 'min:3', 'max:4'],
            'attitude_levels.*.label' => ['required', 'string', 'max:30'],
            'attitude_levels.*.points' => ['required', 'integer', 'min:1', 'max:100'],
            'progress_levels' => ['required', 'array', 'min:3', 'max:5'],
            'progress_levels.*.label' => ['required', 'string', 'max:20'],
            'progress_levels.*.color' => ['required', Rule::in(RatingSettings::KLEURENPALET)],
        ], [
            'effort_levels.min' => 'Geef minstens drie niveaus voor inzet.',
            'effort_levels.max' => 'Hooguit vier niveaus voor inzet.',
            'attitude_levels.min' => 'Geef minstens drie niveaus voor houding.',
            'attitude_levels.max' => 'Hooguit vier niveaus voor houding.',
            'progress_levels.min' => 'Geef minstens drie kleuren.',
            'progress_levels.max' => 'Hooguit vijf kleuren.',
            '*.*.label.required' => 'Elk niveau heeft een naam nodig.',
            '*.*.points.min' => 'Elk niveau levert minstens één punt op.',
        ], [
            'attendance_points' => 'Punten voor aanwezig',
        ]);

        $treden = fn (array $lijst) => array_values(array_map(fn (array $trede, int $i) => [
            'key' => 'n'.($i + 1),
            'label' => trim($trede['label']),
            'points' => (int) $trede['points'],
        ], $lijst, array_keys(array_values($lijst))));

        $school = $request->user()->school;

        $school->forceFill([
            'rating_settings' => array_merge($school->rating_settings ?? [], [
                'grading' => $data['grading'],
                'xp_attendance' => (int) $data['attendance_points'],
                'effort_levels' => $treden(array_values($data['effort_levels'])),
                'attitude_levels' => $treden(array_values($data['attitude_levels'])),
                'progress_levels' => array_values(array_map(fn (array $niveau, int $i) => [
                    'key' => 'n'.($i + 1),
                    'label' => trim($niveau['label']),
                    'color' => $niveau['color'],
                ], array_values($data['progress_levels']), array_keys(array_values($data['progress_levels'])))),
            ]),
        ])->save();

        return back()->with('status', 'De spelerskaart is bijgewerkt. Nieuwe puntenwaarden gelden vanaf de volgende training.');
    }
}
