<?php

namespace App\Http\Controllers\Goals;

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

        $validated = $request->validate([
            'category' => ['required', Rule::in(ReportCategory::valuesForPosition($player->position))],
            // De trainer denkt in rapportcijfers (1-10); op de kaart is dat maal tien.
            'target' => ['required', 'integer', 'between:1,10'],
            'due_on' => ['required', 'date', 'after:today', 'before:'.now()->addYear()->toDateString()],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'category.in' => 'Deze categorie hoort niet bij de positie van de speler.',
            'due_on.after' => 'De einddatum moet in de toekomst liggen.',
            'due_on.before' => 'Stel een doel voor maximaal een jaar.',
        ], [
            'category' => 'De categorie',
            'target' => 'Het streefcijfer',
            'due_on' => 'De einddatum',
            'note' => 'De toelichting',
        ]);

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

    public function destroy(Goal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        abort_unless($goal->isActive(), 422, 'Alleen een actief doel kun je stoppen.');

        $goal->forceFill(['status' => GoalStatus::Cancelled])->save();

        return back()->with('status', 'Het doel is gestopt.');
    }
}
