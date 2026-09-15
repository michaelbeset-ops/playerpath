<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\RemoveDemoData;
use App\Actions\Onboarding\SeedDemoData;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Dashboard\FamilyDashboard;
use App\Support\Onboarding\OnboardingState;
use App\Support\Trainings\FamilyTrainings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
     * klaar was - het blok zou gewoon verdwenen zijn.
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

        OnboardingState::save($request->user()->school, ['tour_seen_at' => null, 'tour_step' => 0]);

        return redirect()->route('dashboard');
    }

    /**
     * Onthouden waar iemand in de rondleiding is.
     *
     * De rondleiding loopt over veertien schermen. Wie halverwege de telefoon
     * wegstopt hoort bij stap acht te kunnen hervatten, niet bij stap één.
     */
    public function tourStep(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar() || $request->user()->isTrainer(), 403);

        $data = $request->validate(['step' => ['required', 'integer', 'min:0', 'max:50']]);

        OnboardingState::save($request->user()->school, ['tour_step' => (int) $data['step']]);

        return back();
    }

    /**
     * Wat een ouder ziet - voor de eigenaar.
     *
     * Dit is zijn verkoopargument, en hij kan het nergens anders zien: hij is
     * geen ouder. Dus tekenen we het gezinsdashboard voor hem, met de
     * voorbeeldspelers als "zijn kinderen". Dezelfde componenten en dezelfde
     * rekenklassen als het echte ouderscherm, zodat het niet kan afwijken.
     */
    public function parentPreview(Request $request, FamilyDashboard $family): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $kinderen = Player::query()->demo()->orderBy('first_name')->limit(2)->pluck('id')->all();

        if ($kinderen === []) {
            $kinderen = Player::query()->active()->whereNotNull('overall_rating')->orderBy('first_name')->limit(2)->pluck('id')->all();
        }

        return Inertia::render('Dashboard', [
            'view' => 'gezin',
            'preview' => true,
            'children' => $family->children($kinderen),
            'upcoming' => $family->upcomingTrainings($request->user(), $kinderen),
            'offerings' => $family->openOfferings($request->user()),
            'messages' => $family->messages($request->user()),
        ]);
    }

    /**
     * Je eigen inschrijfpagina, zoals een ouder hem ziet.
     *
     * De echte pagina in een kader, niet een nagebouwde: wat de eigenaar hier
     * ziet is precies wat een ouder krijgt als hij de link deelt.
     */
    public function cardChoice(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        // Niets voorgekozen zolang de school nog niet koos.
        return Inertia::render('onboarding/CardChoice', [
            'cardMode' => $request->user()->school->rating_settings['card_mode'] ?? null,
        ]);
    }

    /**
     * Je eigen inschrijfpagina, voor de rondleiding.
     */
    public function enrollPreview(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;

        // Niet 'school': dat is de gedeelde prop met id en naam, en die zou
        // hier overschreven worden. De rondleiding leest daar het id uit.
        return Inertia::render('onboarding/EnrollPreview', [
            'url' => route('enroll.show', $school->slug),
            'schoolName' => $school->name,
        ]);
    }

    /**
     * De trainingen zoals een ouder ze ziet: komend, inschrijven, geweest.
     *
     * Met de voorbeeldspelers als kinderen, zodat er iets in "Inschrijven"
     * staat: daar meldt een ouder zijn kind met één tik aan voor een losse
     * training en kiest hij online of contant. Een oudere school zonder open
     * voorbeeldtraining krijgt er hier een bij, zolang de voorbeelddata er is.
     */
    public function parentTrainingsPreview(Request $request, FamilyTrainings $gezin, SeedDemoData $demo): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $demo->ensureOpenTraining($request->user()->school);

        $kinderen = Player::query()->demo()->orderBy('first_name')->limit(2)->pluck('id')->all();

        if ($kinderen === []) {
            $kinderen = Player::query()->active()->orderBy('first_name')->limit(2)->pluck('id')->all();
        }

        return Inertia::render('trainings/Index', [
            ...$gezin->for($request->user(), $kinderen),
            'preview' => true,
            'canManage' => false,
            'canRecord' => false,
            'isParticipant' => true,
            'canEnroll' => true,
            'filters' => ['group' => null, 'trainer' => null],
            'groups' => [],
            'trainers' => [],
        ]);
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
