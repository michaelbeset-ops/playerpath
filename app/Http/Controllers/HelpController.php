<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Schools\EnrollmentSettingsController;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De hulppagina: hoe het werkt, in gewone taal, per rol.
 *
 * Een school leest hoe spelers, ouders, inschrijven en de kaart aan elkaar
 * hangen; een ouder leest wat hij ziet en wat hij kan; een speler alleen
 * zijn kaart. De tekst staat in de Vue-pagina, want het is tekst - de
 * server zegt alleen voor wie hij is en wat er bij deze school aanstaat.
 */
class HelpController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Help', [
            'audience' => match (true) {
                $user->isEigenaar() => 'eigenaar',
                $user->isTrainer() => 'trainer',
                $user->isOuder() => 'ouder',
                default => 'speler',
            },
            'schoolName' => $user->school?->name,
            // Ons nummer is voor de school. Een ouder, kind of trainer belt
            // zijn eigen voetbalschool; die kent het kind en de afspraken.
            'supportPhone' => $user->isEigenaar() ? EnrollmentSettingsController::SUPPORT_PHONE : null,
            'schoolPhone' => $user->school?->contact_phone,
            'schoolEmail' => $user->school?->contact_email,
            // De eerste van de eigen kinderen, zodat "kaart delen" ergens
            // naartoe kan wijzen. Een ouder zonder kind ziet de knop niet.
            'firstChildId' => $user->isOuder() ? $user->children()->value('players.id') : null,
        ]);
    }
}
