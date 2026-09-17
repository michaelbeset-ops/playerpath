<?php

namespace App\Http\Controllers\Trainings;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Support\Trainings\ReportPrompts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mijn trainingen: alleen wat van deze trainer is.
 *
 * Het rooster van de school staat op /trainings en blijft daar; dit is het
 * scherm dat een trainer op zijn telefoon openslaat als hij wil weten waar hij
 * vanmiddag moet zijn. Alleen zijn eigen trainingen, op volgorde van tijd, met
 * de eerstvolgende bovenaan.
 *
 * Een training zonder gekoppelde trainers telt als "van iedereen". Koppelen is
 * informatief (zie CLAUDE.md), en veel scholen doen het niet; zou dit scherm
 * strikt filteren, dan is het bij die scholen altijd leeg.
 */
class MyTrainingsController extends Controller
{
    public function __construct(protected ReportPrompts $prompts) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Training::class);

        $user = $request->user();

        $trainingen = Training::query()
            ->with(['group', 'trainers', 'slot.product', 'slot.player'])
            ->where('starts_at', '>=', now()->startOfDay())
            // Dezelfde regel als in de kalender, uit één plek (Training::scopeForTrainer).
            ->forTrainer($user)
            ->orderBy('starts_at')
            ->limit(50)
            ->get()
            ->map(fn (Training $training) => [
                'id' => $training->id,
                'group' => $training->label(),
                'date' => $training->starts_at->translatedFormat('l j F'),
                'day' => $training->starts_at->translatedFormat('D'),
                'dayNumber' => $training->starts_at->format('j'),
                'month' => $training->starts_at->translatedFormat('M'),
                'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
                'location' => $training->location,
                'cancelled' => $training->isCancelled(),
                'cancellationReason' => $training->cancellation_reason,
                'trainers' => $training->trainers->pluck('name')->all(),
                'isToday' => $training->starts_at->isToday(),
                'expected' => $training->expectedPlayers()->count(),
            ])
            ->values();

        return Inertia::render('trainings/Mine', [
            'trainings' => $trainingen,
            // Dezelfde herinnering als op het dashboard: dit is de plek waar
            // een trainer na afloop naartoe gaat.
            'reportPrompts' => $this->prompts->for($user),
        ]);
    }
}
