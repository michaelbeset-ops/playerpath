<?php

namespace App\Support\Exports;

use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Schrijft een Export weg als CSV of Excel, rechtstreeks naar de browser.
 *
 * **Excel is een net document**, geen kale dump: bovenaan de naam van de
 * school, de titel en de periode; vette kolomkoppen met een lichte achtergrond;
 * kolommen breed genoeg voor wat erin staat; datums als echte datums
 * (dd-mm-jjjj), bedragen met euroteken en twee decimalen; en waar dat zin
 * heeft een totaalregel onderaan. Dat is wat een boekhouder of een bestuur
 * verwacht te openen.
 *
 * **CSV blijft kaal**: puntkomma en een BOM, alleen de kolomkoppen en de
 * rijen. CSV is voor importeren in een ander programma, en een kopregel met
 * de schoolnaam zit daar alleen maar in de weg. Met een komma als scheider
 * zou Nederlands Excel alles in één kolom proppen, want de komma is hier het
 * decimaalteken.
 *
 * De bestandsnaam draagt school, overzicht en datum: "keepersschool-rob-
 * betalingen-2026-09-11.xlsx" is over een half jaar nog te vinden.
 */
class ExportWriter
{
    public const FORMATS = ['csv', 'xlsx'];

    public function __construct(protected Tenancy $tenancy) {}

    /** @param  array{from?: string|null, to?: string|null}  $filters */
    public function download(Export $export, string $format, array $filters): StreamedResponse
    {
        $format = in_array($format, self::FORMATS, strict: true) ? $format : 'xlsx';

        $school = $this->tenancy->school()?->name;
        $bestandsnaam = implode('-', array_filter([
            $school ? Str::slug($school) : null,
            Str::slug($export->title()),
            now()->format('Y-m-d'),
        ])).'.'.$format;

        return response()->streamDownload(function () use ($export, $format, $filters, $school) {
            $format === 'csv'
                ? $this->schrijfCsv($export, $filters)
                : $this->schrijfXlsx($export, $filters, $school);
        }, $bestandsnaam, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function schrijfCsv(Export $export, array $filters): void
    {
        $writer = new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';', SHOULD_ADD_BOM: true));
        $writer->openToFile('php://output');
        $writer->addRow(Row::fromValues($export->headings()));

        foreach ($export->rows($filters) as $rij) {
            $writer->addRow(Row::fromValues(array_map(fn ($w) => $w ?? '', array_values($rij))));
        }

        $writer->close();
    }

    protected function schrijfXlsx(Export $export, array $filters, ?string $school): void
    {
        $sheets = $export instanceof WorkbookExport
            ? $export->sheets($filters)
            : [$this->hoofdblad($export, $filters)];

        $opties = new XlsxOptions;
        $writer = new XlsxWriter($opties);
        $writer->openToFile('php://output');

        $periode = $this->periode($export, $filters);

        $kop = (new Style)->withFontBold(true)->withFontSize(14);
        $subkop = (new Style)->withFontColor('5A677D');
        $koppen = (new Style)->withFontBold(true)->withBackgroundColor('E2E8F0');
        $totaal = (new Style)->withFontBold(true)->withBackgroundColor('F1F5F9');

        foreach ($sheets as $index => $sheet) {
            if ($index > 0) {
                $writer->addNewSheetAndMakeItCurrent();
            }

            $blad = $writer->getCurrentSheet();
            $blad->setName(mb_substr($sheet->title, 0, 31));

            $aantalKolommen = max(1, count($sheet->headings));

            // De kop: school, titel en periode. Over de volle breedte, zodat
            // een lange schoolnaam niet in kolom A klem zit.
            $writer->addRow(Row::fromValuesWithStyle([$school ?? config('app.name')], $kop));
            $writer->addRow(Row::fromValuesWithStyle([$export->title().' - '.$sheet->title.'  ·  '.$periode.'  ·  gemaakt op '.now()->format('d-m-Y H:i')], $subkop));
            $writer->addRow(Row::fromValues(['']));

            if ($aantalKolommen > 1) {
                $opties->mergeCells(0, 1, $aantalKolommen - 1, 1, $index);
                $opties->mergeCells(0, 2, $aantalKolommen - 1, 2, $index);
            }

            $writer->addRow(Row::fromValuesWithStyle($sheet->headings, $koppen));

            // Rijen eerst verzamelen: de kolombreedtes en de totalen hebben ze
            // allemaal nodig. Een schooloverzicht is hooguit enkele duizenden
            // regels, dat past ruim.
            $rijen = [];
            foreach ($sheet->rows as $rij) {
                $rijen[] = array_values($rij);
            }

            $breedtes = array_map(fn (string $h) => mb_strlen($h) + 2, $sheet->headings);
            $sommen = array_fill_keys($sheet->totals, 0);

            foreach ($rijen as $rij) {
                $cellen = [];

                foreach ($rij as $i => $waarde) {
                    $soort = $sheet->types[$i] ?? 'text';
                    $cellen[] = $this->cel($waarde, $soort);
                    $breedtes[$i] = min(60, max($breedtes[$i] ?? 8, mb_strlen((string) ($waarde ?? '')) + 2));

                    if (array_key_exists($i, $sommen) && is_numeric($waarde)) {
                        $sommen[$i] += $waarde;
                    }
                }

                $writer->addRow(new Row($cellen));
            }

            if ($sheet->totals !== [] && $rijen !== []) {
                $cellen = [];

                for ($i = 0; $i < $aantalKolommen; $i++) {
                    $cellen[] = match (true) {
                        $i === 0 => Cell::fromValue('Totaal', $totaal),
                        array_key_exists($i, $sommen) => $this->cel(round($sommen[$i], 2), $sheet->types[$i] ?? 'number', $totaal),
                        default => Cell::fromValue('', $totaal),
                    };
                }

                $writer->addRow(new Row($cellen));
            }

            foreach ($breedtes as $i => $breedte) {
                $blad->setColumnWidth((float) $breedte, $i + 1);
            }
        }

        $writer->close();
    }

    /** Een cel met de opmaak die bij de soort hoort. */
    protected function cel(mixed $waarde, string $soort, ?Style $basis = null): Cell
    {
        $stijl = $basis ?? new Style;

        if ($waarde === null || $waarde === '') {
            return Cell::fromValue('', $basis);
        }

        return match ($soort) {
            'money' => Cell::fromValue((float) $waarde, $stijl->withFormat('"€ "#,##0.00')),
            'number' => Cell::fromValue(is_numeric($waarde) ? $waarde + 0 : $waarde, $stijl->withFormat('0.0')),
            'int' => Cell::fromValue(is_numeric($waarde) ? (int) $waarde : $waarde, $stijl->withFormat('0')),
            'date' => $this->datum($waarde, $stijl),
            default => Cell::fromValue(is_numeric($waarde) && ! is_string($waarde) ? $waarde : (string) $waarde, $basis),
        };
    }

    /** "11-09-2026" wordt een echte datumcel; anders blijft het tekst. */
    protected function datum(mixed $waarde, Style $stijl): Cell
    {
        if (is_string($waarde) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $waarde)) {
            $datum = DateTimeImmutable::createFromFormat('!d-m-Y', $waarde);

            if ($datum !== false) {
                return Cell::fromValue($datum, $stijl->withFormat('dd-mm-yyyy'));
            }
        }

        return Cell::fromValue((string) $waarde);
    }

    protected function hoofdblad(Export $export, array $filters): Sheet
    {
        return new Sheet(
            $export->title(),
            $export->headings(),
            $export->rows($filters),
            $export instanceof FormattedExport ? $export->types() : [],
            $export instanceof FormattedExport ? $export->totals() : [],
        );
    }

    protected function periode(Export $export, array $filters): string
    {
        if (! $export->supportsDateRange() || (empty($filters['from']) && empty($filters['to']))) {
            return 'alle gegevens';
        }

        $van = ! empty($filters['from']) ? CarbonImmutable::parse($filters['from'])->format('d-m-Y') : '…';
        $tot = ! empty($filters['to']) ? CarbonImmutable::parse($filters['to'])->format('d-m-Y') : '…';

        return "periode {$van} t/m {$tot}";
    }
}
