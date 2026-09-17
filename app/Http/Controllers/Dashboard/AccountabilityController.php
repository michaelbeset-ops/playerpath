<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\Dashboard\SchoolAccountability;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het verantwoordingsoverzicht: wat de school over een periode heeft gedaan.
 *
 * Alleen de eigenaar. Dit gaat over de school als geheel en is bedoeld om naar
 * buiten te laten zien; een trainer hoort dat niet namens de school te doen.
 */
class AccountabilityController extends Controller
{
    public function __construct(protected SchoolAccountability $overzicht) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'De einddatum moet na de begindatum liggen.',
        ]);

        // Standaard het lopende seizoen tot vandaag: dat is de periode waarover
        // een school zich in de praktijk verantwoordt.
        $vanaf = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : $this->seizoenStart();

        $tot = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : CarbonImmutable::now();

        return Inertia::render('accountability/Index', [
            'report' => $this->overzicht->for($vanaf, $tot),
            'schoolInfo' => $request->user()->school->only(['name']),
            'range' => ['from' => $vanaf->toDateString(), 'to' => $tot->toDateString()],
        ]);
    }

    /** Een voetbalseizoen loopt van augustus tot augustus. */
    private function seizoenStart(): CarbonImmutable
    {
        $nu = CarbonImmutable::now();

        return $nu->month >= 8
            ? $nu->setDate($nu->year, 8, 1)->startOfDay()
            : $nu->setDate($nu->year - 1, 8, 1)->startOfDay();
    }
}
