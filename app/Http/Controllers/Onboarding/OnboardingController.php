<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\RemoveDemoData;
use App\Http\Controllers\Controller;
use App\Support\Onboarding\OnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * De knoppen van het opstarten: wegklikken, terughalen, opruimen.
 *
 * Klein met opzet. De stand staat in `schools.onboarding` (of, voor het
 * welkomstregeltje van een ouder, op de gebruiker zelf), en dit is de enige
 * plek die hem zet.
 */
class OnboardingController extends Controller
{
    /** De startchecklist wegklikken. Terughalen kan; zie herstel(). */
    public function dismissChecklist(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        OnboardingState::mark($request->user()->school, 'checklist_dismissed_at');

        return back()->with('status', 'De startlijst staat nu onderaan je instellingen. Je kunt hem daar terughalen.');
    }

    public function restoreChecklist(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        OnboardingState::clear($request->user()->school, 'checklist_dismissed_at');

        return redirect()->route('dashboard')->with('status', 'De startlijst staat weer op je dashboard.');
    }

    /**
     * De checklist is af: de felicitatie is gezien en hij komt niet meer terug.
     *
     * Het scherm meldt dit zelf zodra alle stappen gedaan zijn. Zou de server
     * het meteen bij het laatste vinkje wegzetten, dan zag niemand ooit dat hij
     * klaar was — het blok zou gewoon verdwenen zijn.
     */
    public function completeChecklist(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        OnboardingState::mark($request->user()->school, 'checklist_completed_at');

        return back();
    }

    /** De rondleiding is afgerond of overgeslagen. Opnieuw starten kan altijd. */
    public function finishTour(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar() || $request->user()->isTrainer(), 403);

        OnboardingState::mark($request->user()->school, 'tour_seen_at');

        return back();
    }

    public function restartTour(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar() || $request->user()->isTrainer(), 403);

        OnboardingState::clear($request->user()->school, 'tour_seen_at');

        return redirect()->route('dashboard');
    }

    /**
     * Het welkomstregeltje van een ouder of speler: één keer, en daarna nooit.
     *
     * Per gebruiker en niet per school: het gaat erover of déze persoon het al
     * gezien heeft.
     */
    public function dismissIntro(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['intro_seen_at' => now()])->save();

        return back();
    }

    /**
     * De voorbeelddata er in één keer uit.
     *
     * Alleen de eigenaar, en met een bevestiging op het scherm ervoor: het gaat
     * om verwijderen, en dat is niet terug te draaien.
     */
    public function removeDemo(Request $request, RemoveDemoData $actie): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;

        $geteld = $actie->handle($school);
        $actie->finish($school);

        return redirect()->route('dashboard')->with(
            'status',
            'De voorbeelddata is opgeruimd: '.$geteld['players'].' spelers, '.$geteld['reports'].
            ' rapporten en '.$geteld['trainings'].' trainingen. Je school is nu helemaal van jou.'
        );
    }
}
