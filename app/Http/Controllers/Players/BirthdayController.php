<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Dashboard\SchoolDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wie er binnenkort jarig is.
 *
 * Voor een trainer is dit een eigen scherm: hij feliciteert het kind zelf
 * langs de lijn, en dat is precies waar hij zijn telefoon pakt. Voor de
 * eigenaar staat hetzelfde als widget op zijn dashboard; dit scherm is de
 * "Bekijk meer" daarachter.
 *
 * Een trainer ziet alleen zijn eigen spelers (TrainerScope, via
 * `birthdays(for:)`), de eigenaar de hele school.
 */
class BirthdayController extends Controller
{
    /** Hoe ver vooruit. Twee maanden is genoeg om te weten wie er bij het kamp jarig is. */
    public const DAGEN = 60;

    public function __construct(protected SchoolDashboard $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        return Inertia::render('players/Birthdays', [
            'birthdays' => $this->dashboard->birthdays(days: self::DAGEN, limit: 200, for: $request->user()),
            'days' => self::DAGEN,
        ]);
    }
}
