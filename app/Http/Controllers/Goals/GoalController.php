<?php

namespace App\Http\Controllers\Goals;

use App\Actions\Goals\AchieveGoal;
use App\Enums\GoalStatus;
use App\Enums\ReportCategory;
use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Doelen stellen en stoppen. Het beoordelen (gehaald / verlopen) gebeurt
 * automatisch na elk rapport, zie Actions\Goals\EvaluateGoals.
 */
class GoalController extends Controller
{
    public function store(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('createFor', [Goal::class, $player]);

        $eigenDoel = $request->string('category')->toString() === Goal::CUSTOM;

        $validated = $request->validate([
            'category' => ['required', Rule::in([...ReportCategory::valuesForPosition($player->position), Goal::CUSTOM])],
            // Een eigen doel is een zin, geen cijfer: dan is de omschrijving
            // het doel en heeft een streefcijfer nergens betrekking op.
            'custom_label' => [Rule::requiredIf($eigenDoel), 'nullable', 'string', 'max:60'],
            // De trainer denkt in rapportcijfers (1-10); op de kaart is dat maal tien.
            'target' => [Rule::requiredIf(! $eigenDoel), 'nullable', 'integer', 'between:1,10'],
            'due_on' => ['required', 'date', 'after:today', 'before:'.now()->addYear()->toDateString()],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'category.in' => 'Deze categorie hoort niet bij de positie van de speler.',
            'custom_label.required' => 'Schrijf op waar dit doel over gaat.',
            'due_on.after' => 'De einddatum moet in de toekomst liggen.',
            'due_on.before' => 'Stel een doel voor maximaal een jaar.',
        ], [
            'category' => 'De categorie',
            'custom_label' => 'De omschrijving',
            'target' => 'Het streefcijfer',
            'due_on' => 'De einddatum',
            'note' => 'De toelichting',
        ]);

        if ($eigenDoel) {
            // Meerdere eigen doelen naast elkaar mag: het zijn verschillende
            // dingen, geen twee metingen van dezelfde categorie.
            $player->goals()->create([
                'set_by_id' => $request->user()->id,
                'category' => Goal::CUSTOM,
                'custom_label' => $validated['custom_label'],
                'start_rating' => 0,
                'target_rating' => null,
                'starts_on' => now()->toDateString(),
                'due_on' => $validated['due_on'],
                'note' => $validated['note'] ?? null,
            ]);

            return back()->with('status', 'Het doel is gesteld. Vink het zelf af zodra het gehaald is.');
        }

        $huidig = ($player->category_ratings ?? [])[$validated['category']] ?? 0;
        $streef = $validated['target'] * 10;

        if ($huidig >= $streef) {
            return back()->withErrors(['target' => "Het huidige cijfer is al {$huidig}. Kies een hoger streefcijfer."]);
        }

        // Eén actief doel per categorie: anders wordt "op koers" onleesbaar.
        $player->goals()->active()->where('category', $validated['category'])->update(['status' => GoalStatus::Cancelled->value]);

        $player->goals()->create([
            'set_by_id' => $request->user()->id,
            'category' => $validated['category'],
            'start_rating' => $huidig,
            'target_rating' => $streef,
            'starts_on' => now()->toDateString(),
            'due_on' => $validated['due_on'],
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('status', 'Het doel is gesteld. De ouders zien het op de kaart.');
    }

    /**
     * Een eigen doel afvinken.
     *
     * Alleen voor doelen zonder cijfer: de andere gaan vanzelf, en met de hand
     * kunnen afvinken zou betekenen dat de kaart en het doel iets anders
     * kunnen zeggen over dezelfde categorie.
     */
    public function achieve(Request $request, Goal $goal, AchieveGoal $behalen): RedirectResponse
    {
        $this->authorize('delete', $goal);

        abort_unless($goal->isActive(), 422, 'Alleen een actief doel kun je afvinken.');
        abort_unless($goal->isCustom(), 422, 'Dit doel gaat vanzelf zodra het cijfer er is.');

        $behalen->handle($goal);

        return back()->with('status', 'Het doel staat op behaald. De ouders krijgen bericht.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        abort_unless($goal->isActive(), 422, 'Alleen een actief doel kun je stoppen.');

        $goal->forceFill(['status' => GoalStatus::Cancelled])->save();

        return back()->with('status', 'Het doel is gestopt.');
    }
}
