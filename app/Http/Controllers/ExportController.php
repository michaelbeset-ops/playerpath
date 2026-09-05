<?php

namespace App\Http\Controllers;

use App\Support\Exports\Export;
use App\Support\Exports\ExportRegistry;
use App\Support\Exports\ExportWriter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Overzichten exporteren naar CSV of Excel.
 *
 * Alleen de eigenaar: dit zijn de gegevens van de hele school, inclusief
 * namen en e-mailadressen van ouders.
 */
class ExportController extends Controller
{
    public function __construct(
        protected ExportRegistry $registry,
        protected ExportWriter $writer,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        return Inertia::render('exports/Index', [
            'exports' => array_map(fn (Export $export) => [
                'key' => $export->key(),
                'title' => $export->title(),
                'description' => $export->description(),
                'supportsDateRange' => $export->supportsDateRange(),
            ], $this->registry->all()),
            // Standaard de laatste drie maanden; dat is wat je meestal wilt zien.
            'defaultRange' => [
                'from' => now()->subMonths(3)->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ],
        ]);
    }

    public function download(Request $request, string $key): StreamedResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        abort_unless($this->registry->has($key), 404);

        $validated = $request->validate([
            'format' => ['nullable', 'in:csv,xlsx'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'De einddatum moet na de begindatum liggen.',
        ], [
            'from' => 'De begindatum',
            'to' => 'De einddatum',
        ]);

        $export = $this->registry->find($key);

        return $this->writer->download($export, $validated['format'] ?? 'xlsx', [
            'from' => $export->supportsDateRange() ? ($validated['from'] ?? null) : null,
            'to' => $export->supportsDateRange() ? ($validated['to'] ?? null) : null,
        ]);
    }
}
