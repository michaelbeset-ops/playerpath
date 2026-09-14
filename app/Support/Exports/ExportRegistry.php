<?php

namespace App\Support\Exports;

use App\Support\Rating\RatingSettings;
use App\Support\Tenancy\Tenancy;
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
        EffortExport::class,
        CourseProgressExport::class,
        FinancialExport::class,
    ];

    /**
     * Overzichten die bij één soort spelerskaart horen. Een school met de
     * inzetkaart heeft geen rapportcijfers om te exporteren, en andersom.
     *
     * @var array<class-string<Export>, string>
     */
    protected array $alleenBij = [
        ReportsExport::class => RatingSettings::PRESTATIE,
        EffortExport::class => RatingSettings::INZET,
        CourseProgressExport::class => RatingSettings::INZET,
    ];

    /** @return list<Export> */
    public function all(): array
    {
        $modus = RatingSettings::for(app(Tenancy::class)->school())->cardMode();

        return array_values(array_map(
            fn (string $klasse) => app($klasse),
            array_filter($this->exports, fn (string $klasse) => ! isset($this->alleenBij[$klasse]) || $this->alleenBij[$klasse] === $modus),
        ));
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
