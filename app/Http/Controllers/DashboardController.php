<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het dashboard toont alleen cijfers die nu echt bestaan.
 *
 * Het volledige eigenaar-dashboard (omzet, snelle acties, overzichten) is
 * fase 6; hier staan bewust alleen de tellingen die nu echt bestaan.
 * De queries zijn automatisch gefilterd op de actieve school.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'players' => Player::active()->count(),
                'reportsThisWeek' => Report::where('reported_on', '>=', now()->startOfWeek())->count(),
                'trainingsThisWeek' => Training::whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
        ]);
    }
}
