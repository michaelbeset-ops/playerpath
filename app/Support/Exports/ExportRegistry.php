<?php

namespace App\Support\Exports;

use InvalidArgumentException;

/**
 * Alle overzichten die geëxporteerd kunnen worden, in de volgorde van het scherm.
 *
 * Een nieuw overzicht toevoegen: een Export-klasse schrijven en hier in de
 * lijst zetten. Meerdere tabbladen? Implementeer WorkbookExport.
 */
class ExportRegistry
{
    /** @var list<class-string<Export>> */
    protected array $exports = [
        PlayersExport::class,
        TrainingsExport::class,
        AttendanceExport::class,
        ReportsExport::class,
        FinancialExport::class,
    ];

    /** @return list<Export> */
    public function all(): array
    {
        return array_map(fn (string $klasse) => app($klasse), $this->exports);
    }

    public function find(string $key): Export
    {
        foreach ($this->all() as $export) {
            if ($export->key() === $key) {
                return $export;
            }
        }

        throw new InvalidArgumentException("Onbekend overzicht: {$key}");
    }

    public function has(string $key): bool
    {
        return collect($this->all())->contains(fn (Export $export) => $export->key() === $key);
    }
}
