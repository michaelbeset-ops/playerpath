<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Exports\ExportWriter;
use App\Support\Exports\PlayerDataExport;
use App\Support\Privacy\RetentionOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * De AVG-kant: hoe lang bewaren we gegevens, en wat doen we met een verzoek
 * van een ouder om inzage of verwijdering.
 *
 * Alleen de eigenaar. Een trainer hoort niet te beslissen welke gegevens van
 * een oud-lid verdwijnen, en een ouder al helemaal niet die van een ander kind.
 */
class PrivacyController extends Controller
{
    public function __construct(
        protected RetentionOverview $overview,
        protected ExportWriter $writer,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        return Inertia::render('privacy/Index', [
            'retention' => $this->overview->describe($request->user()->school),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $validated = $request->validate([
            // Leeg mag: dan is er nog niets besloten en signaleren we niets.
            // Maximaal tien jaar, want daarboven is het geen termijn meer.
            'retention_months' => ['nullable', 'integer', 'between:1,120'],
        ], [
            'retention_months.between' => 'Kies een termijn tussen 1 en 120 maanden.',
        ], [
            'retention_months' => 'De bewaartermijn',
        ]);

        $request->user()->school->update([
            'retention_months' => $validated['retention_months'] ?? null,
        ]);

        return back()->with('status', 'De bewaartermijn is opgeslagen.');
    }

    /**
     * Inzageverzoek: alles wat we van deze speler bewaren, in één werkmap.
     */
    public function download(Request $request, Player $player): StreamedResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        $this->authorize('view', $player);

        return $this->writer->download(new PlayerDataExport($player), 'xlsx', []);
    }

    /**
     * Verwijderverzoek. Dit is onomkeerbaar en neemt de rapporten mee, dus het
     * loopt via dezelfde policy als het verwijderen op de spelerspagina.
     */
    public function erase(Request $request, Player $player): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        $this->authorize('delete', $player);

        $naam = $player->full_name;

        $player->delete();

        return back()->with('status', "Alle gegevens van {$naam} zijn definitief verwijderd.");
    }
}
