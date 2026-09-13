<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Impersonation;
use App\Models\PlatformLog;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het logboek: wat je als beheerder hebt gedaan.
 *
 * Twee bronnen naast elkaar. Beheeracties zijn losse gebeurtenissen op één
 * moment; een impersonatie is een sessie met een begin en een eind. Ze delen
 * geen vorm, dus ook geen tabel - maar je wilt ze wel op één plek terugzien.
 */
class LogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('platform.manageSchools');

        $schoolId = $request->integer('school') ?: null;

        return Inertia::render('platform/Logs', [
            'logs' => PlatformLog::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn (PlatformLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'summary' => $log->summary,
                    // De naam als tekst: die overleeft het verwijderen van de
                    // school, en juist die regel wil je later terugvinden.
                    'school' => $log->school_name,
                    'school_id' => $log->school_id,
                    'admin' => $log->admin_email,
                    'when' => $log->created_at->format('d-m-Y H:i'),
                    'details' => $log->details,
                ]),
            'impersonations' => Impersonation::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->latest('started_at')
                ->limit(50)
                ->get()
                ->map(fn (Impersonation $log) => [
                    'id' => $log->id,
                    'user_email' => $log->user_email,
                    'admin' => $log->admin_email,
                    'school' => $log->school?->name,
                    'started_at' => $log->started_at->format('d-m-Y H:i'),
                    'ended_at' => $log->ended_at?->format('H:i'),
                ]),
            'schools' => School::orderBy('name')->get(['id', 'name'])
                ->map(fn (School $school) => ['id' => $school->id, 'name' => $school->name]),
            'filters' => ['school' => $schoolId],
        ]);
    }
}
