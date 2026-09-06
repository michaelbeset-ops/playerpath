<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Package;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Impersonation;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Support\Money\Money;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het platformoverzicht: alle scholen bij elkaar.
 *
 * Dezelfde regel als op het schooldashboard: alleen cijfers die echt bestaan.
 *
 * De omzet is wat de pakketten van de *actieve* scholen per maand waard zijn,
 * exclusief btw. Dat is geen gefactureerd bedrag — PlayerPath stuurt zichzelf
 * nog geen rekeningen — maar wel een getal dat ergens op slaat: elke euro
 * erin hoort bij een school die bestaat en aan staat. Een school zonder
 * pakket telt voor niets mee en wordt apart genoemd, want anders zou het
 * cijfer stilzwijgend te laag zijn zonder dat je weet waarom.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorize('platform.access');

        // In centen optellen, pas bij weergave naar euro's — zie CLAUDE.md 3.2.
        $mrrCents = School::query()
            ->where('is_active', true)
            ->whereNotNull('package')
            ->pluck('package')
            ->sum(fn (string $pakket) => Package::tryFrom($pakket)?->priceCents() ?? 0);

        return Inertia::render('platform/Dashboard', [
            'stats' => [
                'schools' => School::count(),
                'activeSchools' => School::where('is_active', true)->count(),
                'players' => Player::where('is_active', true)->count(),
                'trainers' => User::whereHas('roles', fn ($q) => $q->where('name', Role::Trainer->value))->count(),
                'owners' => User::whereHas('roles', fn ($q) => $q->where('name', Role::Eigenaar->value))->count(),
                'guardians' => User::whereHas('roles', fn ($q) => $q->where('name', Role::Ouder->value))->count(),
                'reportsThisMonth' => Report::where('reported_on', '>=', now()->startOfMonth())->count(),
                'mrrCents' => $mrrCents,
                'mrr' => Money::format($mrrCents),
                'mrrPerYear' => Money::format($mrrCents * 12),
                'withoutPackage' => School::where('is_active', true)->whereNull('package')->count(),
            ],
            // Scholen die opvallen: leeg, of al een tijd zonder rapport. Dat is
            // waar je als platform iets aan hebt — een school die stilvalt zegt
            // je meer dan een school die het goed doet.
            'attention' => School::query()
                ->where('is_active', true)
                ->withCount(['players as players_count' => fn ($q) => $q->where('is_active', true)])
                ->get()
                ->map(fn (School $school) => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'players_count' => $school->players_count,
                    'reason' => $school->players_count === 0 ? 'nog geen spelers' : null,
                ])
                ->filter(fn (array $rij) => $rij['reason'] !== null)
                ->values(),
            'recentImpersonations' => Impersonation::query()
                ->with('school')
                ->latest('started_at')
                ->limit(5)
                ->get()
                ->map(fn (Impersonation $log) => [
                    'id' => $log->id,
                    'user_email' => $log->user_email,
                    'school' => $log->school?->name,
                    'started_at' => $log->started_at->format('d-m-Y H:i'),
                    'ended_at' => $log->ended_at?->format('H:i'),
                ]),
        ]);
    }
}
